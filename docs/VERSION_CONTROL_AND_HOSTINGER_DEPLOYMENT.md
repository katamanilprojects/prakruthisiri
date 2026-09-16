# Version Control and Hostinger Deployment Guide (Release & Safety Runbook)

**Platform:** Prakruthi Siri Production Deployment  
**Hosting Environment:** Hostinger Cloud / Web Hosting (hPanel)  
**Web Server:** LiteSpeed / Apache 2.4+ with `mod_rewrite` & `mod_security`  
**PHP Version:** PHP 8.2 or 8.3 (with `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `curl`)  
**Database:** MySQL 8.0+ / MariaDB 10.11+  

---

## 1. Git Repository Architecture

### 1.1 Production `.gitignore` Specification
To prevent credential leaks, security breaches, and accidental deletion of live customer doorstep photos during code synchronization, the repository must enforce the following `.gitignore` specification at the project root:

```gitignore
# ==============================================================================
# Prakruthi Siri - Production Git Ignore Rules
# ==============================================================================

# ------------------------------------------------------------------------------
# 1. Environment Secrets & Database Credentials
# ------------------------------------------------------------------------------
# Never commit database passwords, API secrets, or local environment overrides
.env
.env.local
.env.development
.env.production
config/db_config.php

# ------------------------------------------------------------------------------
# 2. Operating System & Editor Metadata
# ------------------------------------------------------------------------------
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db
desktop.ini

# IDE & Editor Folders
.idea/
.vscode/
*.sublime-project
*.sublime-workspace
*.swp
*.swo
*~

# ------------------------------------------------------------------------------
# 3. User-Generated Media & Uploads (DO NOT OVERWRITE OR COMMIT)
# ------------------------------------------------------------------------------
# Live customer gate photos and dynamic product assets must be preserved on server
public/uploads/gates/*
uploads/gates/*
!public/uploads/gates/.gitkeep
!public/uploads/gates/.htaccess
!uploads/gates/.gitkeep
!uploads/gates/.htaccess

# Generated/uploaded crop images (keep sample SVGs/WebPs)
public/uploads/products/*
uploads/products/*
!public/uploads/products/.gitkeep
!public/uploads/products/*.svg
!public/uploads/products/*.webp
!uploads/products/.gitkeep
!uploads/products/*.svg
!uploads/products/*.webp

# ------------------------------------------------------------------------------
# 4. Runtime Cache, Locks & Logs
# ------------------------------------------------------------------------------
# Atomic rate-limiting locks and system logs
*.log
error_log
/tmp/
/scratch/
prakruthi_rl_*.lock
*.lock.d

# Large codebase dump/review files
project_review.txt

# ------------------------------------------------------------------------------
# 5. Dependency & Test Artifacts
# ------------------------------------------------------------------------------
vendor/
composer.lock
.phpunit.result.cache
```

---

### 1.2 Clean Branching Strategy

To maintain continuous delivery to Hostinger while preventing untested code from impacting active vegetable ordering windows, the development workflow uses a structured trunk-based branching model:

```
[feature/route-tsp] ──┐
                      ▼
[feature/upi-flow] ───► [staging] ────────► [main] (Production Auto-Deploy)
                          ▲                    │
                          │                    ▼
                      Integration        Hostinger Webhook Pull
                       Testing           (Live Customers & Drivers)
```

1. **`main` (Production Trunk):**
   * Mirrors the exact code executing on the live Hostinger production website (`prakruthisiri.com`).
   * Protected branch: Direct commits to `main` are restricted. All changes merge via Pull Request or after passing local verification.
   * Auto-deployment hook is tied to this branch.
2. **`staging` (Integration & Verification):**
   * Integration environment used to verify scheduled delivery runs, cutoff extensions, and geofence boundary tests with production-like datasets.
   * Runs `php tests/verify_run_pipeline.php` before promoting code to `main`.
3. **`feature/<name>` (Isolated Feature Branches):**
   * Ephemeral branches for distinct capabilities (e.g., `feature/route-tsp-partitioning`, `feature/payment-gateway`).
   * Branched from `main`, merged into `staging`.
4. **`hotfix/<name>` (Emergency Production Patches):**
   * Created directly from `main` to address critical runtime bugs (e.g. `hotfix/cutoff-time-drift`).
   * Merged into `main` and back-ported to `staging`.

---

## 2. Hostinger Deployment Strategies

### Option A: Safe FTP / FileZilla Deployment (Manual / Cold Transfer)

The manual FTP method is useful when deploying without a Git remote, but carries a high risk of accidentally wiping live production database configurations or customer gate photos if directories are blindly mirrored.

#### 1. FileZilla / SFTP Connection Parameters
* **Host:** `ftp.prakruthisiri.com` (or the Hostinger Server IP found in hPanel)
* **Protocol:** `SFTP - SSH File Transfer Protocol` (or `FTPS - Require explicit FTP over TLS`)
* **Port:** `22` (for SFTP) or `21` (for FTPS)
* **Logon Type:** Normal (using Hostinger FTP username & password)
* **Remote Destination Directory:** `/home/u522254309/domains/prakruthisiri.com/public_html/`

#### 2. Exact Transfer Checklist (What to Upload vs. What to Exclude)

| Item / Directory | Transfer Action | Critical Rationale |
| :--- | :---: | :--- |
| `admin/` | **UPLOAD (Overwrite)** | Updates admin dashboards, route planning, inventory sheets. |
| `assets/` | **UPLOAD (Overwrite)** | Updates CSS themes, customer JS engines, icons. |
| `config/database.php` | **UPLOAD (Overwrite)** | Updates PDO connection manager and transaction engine. |
| `config/db_config.php` | 🛑 **DO NOT UPLOAD** | **CRITICAL:** Contains live production DB credentials. Overwriting breaks production database connection! |
| `.env` | 🛑 **DO NOT UPLOAD** | **CRITICAL:** Contains production environment secrets. |
| `database/` | **UPLOAD (Overwrite)** | Uploads `migrate.php` and `schema.sql`. |
| `driver/` | **UPLOAD (Overwrite)** | Updates mobile driver PWA, manifest view, verification API. |
| `docs/` | **OPTIONAL** | Technical documentation (safe to upload or omit). |
| `public/` (Code Files) | **UPLOAD (Overwrite)** | Updates `index.php`, `track.php`, `order-success.php`, `api/`. |
| `public/uploads/gates/` | 🛑 **DO NOT OVERWRITE** | **CRITICAL:** Contains live customer doorstep verification photos taken by drivers. Overwriting deletes customer verification history! |
| `public/uploads/products/`| **UPLOAD (Only New SVG/WebP)**| Upload only new product icons; do not delete existing images. |
| `src/` | **UPLOAD (Overwrite)** | Updates core business services (`OrderService`, `GeoFenceService`, etc.). |
| `index.php`, `track.php` | **UPLOAD (Overwrite)** | Root redirectors. |
| `.htaccess` & `.user.ini` | **UPLOAD (Verify)** | Rewrites root requests to `public/` and enforces script restrictions. |

#### 3. Post-Upload Permissions Assertion
In FileZilla, right-click and set permissions:
* All directories: `755` (`drwxr-xr-x`)
* All PHP and static files: `644` (`-rw-r--r--`)
* Upload storage folder (`public/uploads/gates/`): `755` (writable by web server process)

---

### Option B: Automated Hostinger Git Deployment (Continuous Delivery Webhook) — *Recommended*

Automated Git deployment eliminates human error, avoids slow FTP transfers, and automatically executes atomic pulls from GitHub/GitLab directly into Hostinger `public_html/` upon `git push`.

```
[Developer Machine]
        │
        ├──► git push origin main
        │
        ▼
[GitHub / GitLab Repository]
        │
        ├──► Webhook HTTP POST
        │
        ▼
[Hostinger hPanel Git Deployment Manager]
        │
        └──► Executes: git pull origin main
             • Updates PHP scripts & assets in 2-3 seconds
             • Leaves .env and config/db_config.php intact (via .gitignore)
             • Leaves public/uploads/gates/ untouched
```

#### Step 1: Initialize Git Repository Locally (if not yet tracked)
Open terminal in the project directory:
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/hostinger/prakruthisiri

# Initialize repository and set main branch
git init -b main

# Add all files (ensuring .gitignore is respected)
git add .

# Commit baseline production state
git commit -m "feat(core): baseline production release of Prakruthi Siri platform"

# Link to your remote GitHub / GitLab repository
git remote add origin git@github.com:your-organization/prakruthi-siri.git
git push -u origin main
```

#### Step 2: Configure Git in Hostinger hPanel
1. Log in to **Hostinger hPanel** (`hpanel.hostinger.com`).
2. Select your hosting account and click **Manage Website**.
3. In the left navigation menu, go to **Advanced** $\to$ **Git**.
4. Fill in the deployment form:
   * **Repository:** `https://github.com/your-organization/prakruthi-siri.git` (or SSH URL).
   * **Branch:** `main`
   * **Install Directory:** Leave blank or enter `/` (this maps directly to the root of `public_html`).
5. Click **Create**. Hostinger will perform the initial clone into your `public_html/` directory.

#### Step 3: Create Server Credentials File on Hostinger
Because `config/db_config.php` and `.env` are ignored by Git, you must create them once directly on Hostinger:
1. Open **hPanel $\to$ File Manager**.
2. Open `public_html/config/db_config.php`.
3. Set the production database credentials:
   ```php
   <?php
   declare(strict_types=1);

   return [
       'host'     => 'localhost',
       'port'     => 3306,
       'database' => 'u522254309_prakruthi_siri',
       'username' => 'u522254309_prakruthi_siri',
       'password' => 'Prakruthi_siri1', // Your strong production password
       'charset'  => 'utf8mb4',
   ];
   ```
4. Create `public_html/.env` with matching keys.

#### Step 4: Configure GitHub / GitLab Webhook for Instant Auto-Deploy
1. In Hostinger **hPanel $\to$ Git**, look for your deployed repository.
2. Find the **Webhook URL** field (e.g., `https://srv00.main-hosting.eu/git-deploy/u522254309/webhook?id=...`).
3. Click **Copy**.
4. Go to your **GitHub Repository $\to$ Settings $\to$ Webhooks $\to$ Add Webhook**:
   * **Payload URL:** Paste the Hostinger Webhook URL.
   * **Content type:** `application/json`
   * **Secret:** (Leave blank or enter if Hostinger provided one).
   * **Which events would you like to trigger this webhook?:** Select `Just the push event`.
   * **Active:** Ensure checked.
   * Click **Add webhook**.

#### Step 5: Test Automated Deployment
From your local terminal, make a minor change (e.g. update a comment in `README.md` or a doc):
```bash
git add .
git commit -m "docs: test hostinger automated git deployment webhook"
git push origin main
```
Within 5–15 seconds, GitHub sends the webhook signal to Hostinger, and Hostinger executes `git pull`. The live site updates instantly without any manual FTP transfer!

---

## 3. Production Emergency Rollback Procedure (< 2 Minutes)

If a breaking bug, PHP fatal error, or logic regression is deployed to production during an active ordering window, follow this rapid rollback procedure.

```
[Production Incident Detected (e.g. HTTP 500 on Checkout)]
                         │
                         ▼
[Step 1: Check Last 3 Commits Locally]
  $ git log --oneline -n 3
                         │
                         ▼
[Step 2: Rollback to Last Stable Commit]
  $ git revert HEAD --no-edit
  $ git push origin main
                         │
                         ▼
[Step 3: Webhook Auto-Syncs to Hostinger (15 Seconds)]
                         │
                         ▼
[Step 4: Verify Order Pipeline Online]
  Checkout & Track operational; zero data loss
```

### Method 1: Instant Git Revert & Push (Recommended — 30 Seconds)
Using `git revert` is the safest rollback mechanism because it creates a new forward commit that undoes the bad changes without rewriting commit history:

```bash
# 1. Inspect recent commits to identify the breaking commit hash
git log --oneline -n 5
# Output example:
# a1b2c3d (HEAD -> main) fix(checkout): broken cart query
# e4f5g6h feat(dispatch): stable route sequencer

# 2. Revert the broken commit immediately
git revert a1b2c3d --no-edit

# 3. Push the revert to main
git push origin main
```
*Result:* Hostinger's webhook receives the push and pulls the revert commit immediately. The production server is restored to the stable state in under 30 seconds.

---

### Method 2: Manual Rollback via Hostinger hPanel Git (60 Seconds)
If your local computer is offline or inaccessible:
1. Log in to **Hostinger hPanel $\to$ Advanced $\to$ Git**.
2. Click **View Details** next to your deployed repository.
3. If Hostinger displays the commit history, click the **Rollback** button next to the previous stable commit.
4. If unavailable, click the **Deploy** button to force a clean re-pull of the current branch head.

---

### Method 3: Emergency Single-File FTP Hotfix (60 Seconds)
If a single file (e.g., `src/OrderService.php`) caused a syntax error:
1. Open FileZilla and connect to Hostinger.
2. Locate the stable version of `src/OrderService.php` in your local working directory.
3. Drag and drop the single file into `/public_html/src/OrderService.php`.
4. Overwrite confirmation: Click **Yes**.
5. Test `https://prakruthisiri.com/track.php` immediately.

---

## 4. Post-Deployment Database Migration Execution

Whenever a deployment introduces new schema columns (such as `route_leg_number`) or new index optimizations:
1. SSH into Hostinger (if SSH access is enabled in hPanel):
   ```bash
   ssh u522254309@prakruthisiri.com -p 65002
   cd public_html
   php database/migrate.php
   ```
2. Or trigger idempotent migration automatically:
   The `src/DatabaseMigration.php` class is engineered to be **completely idempotent**. The next administrative login to `admin/index.php` or execution of `tests/verify_run_pipeline.php` invokes `DatabaseMigration::ensureMigrated($pdo)`, which automatically provisions missing tables and columns without dropping existing data.

---

## 5. Pre-Release 10-Point Safety Checklist

Run through this checklist before executing `git push origin main`:

- [ ] **1. Test Pipeline:** `php tests/verify_run_pipeline.php` passes with `7 PASSED, 0 FAILED`.
- [ ] **2. Credential Isolation:** `.env` and `config/db_config.php` are NOT staged in `git status`.
- [ ] **3. Doorstep Photos Preserved:** `public/uploads/gates/` is excluded by `.gitignore`.
- [ ] **4. Geofence Constants Checked:** `GeoFenceService::MAX_RADIUS_KM = 11.5` and `WESTERN_LNG_LIMIT = 79.54000000`.
- [ ] **5. Unit Increments Verified:** All pricing and stock calculations use $0.5\text{ kg}$ packets ($\text{Packets} = \text{Kg} \times 2$).
- [ ] **6. Dual Window Respected:** Cutoff is configured for 19:00 IST on harvest day.
- [ ] **7. Pure Bilingual Integrity:** No slash strings (e.g., `Tomato / టమాటా`) in `CUSTOMER_I18N`.
- [ ] **8. GD Compression Active:** Image upload pipeline re-encodes photos via `imagecreatefromstring` and `imagescale`.
- [ ] **9. UTF-8 BOM Preserved:** Financial and harvest CSV downloads include `\xEF\xBB\xBF`.
- [ ] **10. Script Execution Disabled:** `.htaccess` in `uploads/` denies execution of `.php` files.
