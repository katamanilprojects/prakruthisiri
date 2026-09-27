/**
 * Prakruthi Siri - Farmer Portal Bilingual Dictionary (English & Telugu)
 * Adheres strictly to Zero Combined Bilingual Stacking Standard
 */

const FARMER_I18N = {
  te: {
    portal_title: "రైతు పోర్టల్",
    portal_subtitle: "పొలం ప్లాట్లు & పంట నిర్వహణ",
    nav_plots: "ప్లాట్ల వివరాలు",
    nav_milestones: "పంట దశలు (ఫొటోలు)",
    nav_harvest: "కోత అంచనా నమోదు",
    logout: "లాగ్ అవుట్",
    farmer_label: "రైతు:",

    // Dashboard Plots
    plots_heading: "3 ఎకరాల ప్లాట్ క్వార్టర్లు",
    plots_subtitle: "4 క్వార్టర్ల పంట స్థితి మరియు నిర్వహణ",
    quarter_1: "క్వార్టర్ 1 (0.75 ఎకరం)",
    quarter_2: "క్వార్టర్ 2 (0.75 ఎకరం)",
    quarter_3: "క్వార్టర్ 3 (0.75 ఎకరం)",
    quarter_4: "క్వార్టర్ 4 (0.75 ఎకరం)",
    lbl_current_crop: "ప్రస్తుత పంట:",
    lbl_status: "స్థితి:",
    lbl_sown_date: "విత్తిన తేదీ:",
    lbl_milestones_count: "నమోదైన దశలు:",
    lbl_notes: "గమనికలు:",
    btn_edit_plot: "ప్లాట్ మార్చండి",
    btn_log_photo: "కెమెరా ఫొటో / దశ",
    btn_declare_yield: "కోత దిగుబడి నమోదు",
    no_photo: "ఫొటో లేదు",

    // Statuses
    status_land_preparation: "భూమి తయారీ",
    status_sown: "విత్తనం నాటబడింది",
    status_vegetative: "శాకీయ పెరుగుదల",
    status_flowering: "పూత & కాత దశ",
    status_active_harvesting: "కోత దశ (హార్వెస్టింగ్)",
    status_fallow: "విశ్రాంతి దశ",

    // Milestone stages
    stage_sowing: "విత్తనాలు నాటడం",
    stage_fertilizer_application: "సేంద్రీయ పోషకాలు (జీవామృతం/వేపనూనె)",
    stage_flowering: "పూత & కాత",
    stage_harvesting: "తాజా కూరగాయల కోత",
    stage_other: "పొలం పరిశీలన",

    // Milestone page
    milestone_title: "పంట దశ ఫొటోల నమోదు",
    milestone_subtitle: "మొబైల్ కెమెరాతో పంట దశల ఫొటోలు తీసి రికార్డు చేయండి",
    lbl_select_plot: "ప్లాట్ ఎంచుకోండి:",
    lbl_milestone_stage: "పంట దశ:",
    lbl_capture_photo: "కెమెరా ఫొటో తీయండి:",
    lbl_milestone_notes: "గమనికలు (ఎరువులు / నీరు / పురోగతి):",
    btn_save_milestone: "ఫొటో & దశను సేవ్ చేయండి",
    milestones_history: "గతంలో నమోదైన దశల చరిత్ర",
    no_milestones: "ఇంతవరకు ఎటువంటి దశలు నమోదు కాలేదు.",

    // Harvest page
    harvest_title: "కోత దిగుబడి అంచనా నమోదు",
    harvest_subtitle: "డెలివరీకి 48 గంటల ముందు కోయగల కిలోలను నమోదు చేయండి",
    lbl_delivery_schedule: "డెలివరీ బ్యాచ్ ఎంచుకోండి:",
    lbl_harvest_plot: "కోత జరిగే ప్లాట్:",
    col_produce: "కూరగాయ / పంట",
    col_unit: "కొలత",
    col_harvest_kg: "అంచనా కోత (కిలోలు)",
    col_stock_packs: "అమ్మకానికి ప్యాకెట్లు (0.5kg)",
    btn_publish_inventory: "స్టోర్ ఇన్వెంటరీకి పంపండి",
    harvest_success: "దిగుబడి విజయవంతంగా నమోదైంది మరియు స్టోర్ స్టాక్‌కు చేర్చబడింది!",
  },

  en: {
    portal_title: "Farmer Portal",
    portal_subtitle: "Farm Plots & Crop Management",
    nav_plots: "Plot Quarters",
    nav_milestones: "Crop Milestones",
    nav_harvest: "Harvest Estimation",
    logout: "Sign Out",
    farmer_label: "Farmer:",

    // Dashboard Plots
    plots_heading: "3-Acre Plot Quarters",
    plots_subtitle: "Staggered 4-quarter agronomic status and tracking",
    quarter_1: "Quarter 1 (0.75 Acre)",
    quarter_2: "Quarter 2 (0.75 Acre)",
    quarter_3: "Quarter 3 (0.75 Acre)",
    quarter_4: "Quarter 4 (0.75 Acre)",
    lbl_current_crop: "Current Crop:",
    lbl_status: "Status:",
    lbl_sown_date: "Sown Date:",
    lbl_milestones_count: "Logged Milestones:",
    lbl_notes: "Notes:",
    btn_edit_plot: "Edit Plot",
    btn_log_photo: "Camera Photo / Stage",
    btn_declare_yield: "Declare Harvest Yield",
    no_photo: "No photo yet",

    // Statuses
    status_land_preparation: "Land Preparation",
    status_sown: "Sown",
    status_vegetative: "Vegetative Growth",
    status_flowering: "Flowering & Fruiting",
    status_active_harvesting: "Active Harvesting",
    status_fallow: "Fallow / Rest",

    // Milestone stages
    stage_sowing: "Sowing",
    stage_fertilizer_application: "Organic Input (Jeevamrutham/Neem)",
    stage_flowering: "Flowering & Setting",
    stage_harvesting: "Fresh Vegetable Harvest",
    stage_other: "Plot Inspection",

    // Milestone page
    milestone_title: "Crop Milestone Logging",
    milestone_subtitle: "Capture crop development photos directly via mobile camera",
    lbl_select_plot: "Select Plot:",
    lbl_milestone_stage: "Cultivation Stage:",
    lbl_capture_photo: "Capture / Choose Photo:",
    lbl_milestone_notes: "Notes (Inputs applied, observations):",
    btn_save_milestone: "Save Photo & Milestone",
    milestones_history: "Logged Milestone Timeline",
    no_milestones: "No crop milestones logged for this plot yet.",

    // Harvest page
    harvest_title: "Harvest Yield Forecast Entry",
    harvest_subtitle: "Input estimated harvest quantities 48 hours prior to delivery day",
    lbl_delivery_schedule: "Select Delivery Batch:",
    lbl_harvest_plot: "Harvest Plot Origin:",
    col_produce: "Produce / Crop",
    col_unit: "Unit",
    col_harvest_kg: "Est. Harvest (Kg)",
    col_stock_packs: "Available Packs (0.5kg)",
    btn_publish_inventory: "Publish to Store Inventory",
    harvest_success: "Yield forecast saved and pushed to store inventory successfully!",
  }
};
