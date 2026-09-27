/**
 * Prakruthi Siri - Admin Inventory Stock & Pricing Engine
 * Handles real-time Kg-to-packets auto-calculation and bulk inventory API saving.
 */

document.addEventListener('DOMContentLoaded', () => {
  // Tab 1: Real-time Kg to packet auto-calculation using product unit weight
  document.querySelectorAll('.crop-row').forEach((row) => {
    const kgInput = row.querySelector('.input-kg');
    const pktDisplay = row.querySelector('.packets-display');
    const unitWeight = parseFloat(row.dataset.unitWeight) || 0.5;
    if (kgInput && pktDisplay) {
      kgInput.addEventListener('input', () => {
        const kg = parseFloat(kgInput.value) || 0;
        pktDisplay.textContent = Math.round(kg / (unitWeight > 0 ? unitWeight : 0.5));
      });
    }
  });

  // Tab 1: Bulk Save Inventory
  const btnSave = document.getElementById('btn-save-stock');
  if (btnSave) {
    btnSave.addEventListener('click', async () => {
      const scheduleId = parseInt(btnSave.dataset.scheduleId || '0', 10);
      btnSave.disabled = true;
      btnSave.textContent = 'Saving...';

      const items = [];
      document.querySelectorAll('.crop-row').forEach((row) => {
        items.push({
          id: parseInt(row.dataset.id, 10),
          stock_kg: parseFloat(row.querySelector('.input-kg').value) || 0,
          price: parseFloat(row.querySelector('.input-price').value) || 0,
          is_active: row.querySelector('.input-active').checked ? 1 : 0
        });
      });

      try {
        const resp = await fetch('api/catalog-api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            action: 'bulk_update_inventory',
            schedule_id: scheduleId,
            items: items
          })
        });
        const data = await resp.json();
        if (data.success) {
          alert(data.message || 'Harvest inventory updated successfully.');
          window.location.reload();
        } else {
          alert('Save failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      } finally {
        btnSave.disabled = false;
        btnSave.textContent = '💾 Save Inventory';
      }
    });
  }
});
