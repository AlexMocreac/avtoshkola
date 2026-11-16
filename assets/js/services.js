(() => {
 const serviceDialog = document.getElementById('service-dialog'), folderDialog = document.getElementById('folder-dialog'), form = document.getElementById('service-form'), rows = document.getElementById('allocation-rows');
 const types = JSON.parse(serviceDialog.dataset.balanceTypes), createUrl = form.action;
 let index = 0;
 function addRow(row = {}) {
  const card = document.createElement('section'), i = index++;
  card.className = 'allocation-card';
  const header = document.createElement('div'); header.className = 'allocation-card__header';
  const label = document.createElement('label'); label.className = 'allocation-amount';
  const labelText = document.createElement('span'); labelText.textContent = 'Сумма внесённых оплат, руб.';
  const amount = document.createElement('input'); amount.type = 'number'; amount.min = '0'; amount.step = '0.01'; amount.name = `allocations[${i}][amount]`; amount.placeholder = 'Например, 15 000'; amount.value = row.amount ?? '';
  label.append(labelText, amount);
  const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'allocation-remove'; remove.textContent = 'Удалить'; remove.setAttribute('aria-label', 'Удалить условие начисления');
  remove.onclick = () => { card.remove(); updateEmpty(); };
  header.append(label, remove); card.append(header);
  const title = document.createElement('div'); title.className = 'allocation-card__title'; title.textContent = 'Начисляемые часы'; card.append(title);
  const grid = document.createElement('div'); grid.className = 'allocation-balance-list';
  const addBalance = document.createElement('button'); addBalance.type = 'button'; addBalance.className = 'allocation-add-balance'; addBalance.textContent = '＋ Добавить баланс';
  function refreshOptions() {
   const selects = [...grid.querySelectorAll('select')], selected = selects.map(select => select.value);
   selects.forEach(select => [...select.options].forEach(option => { option.disabled = option.value !== select.value && selected.includes(option.value); }));
   addBalance.disabled = types.every(type => selected.includes(String(type.id)));
  }
  function addBalanceRow(id, value = '') {
   const line = document.createElement('div'); line.className = 'allocation-balance-row';
   const balanceLabel = document.createElement('label'); balanceLabel.textContent = 'Баланс';
   const select = document.createElement('select');
   types.forEach(type => { const option = document.createElement('option'); option.value = type.id; option.textContent = `${type.code} — ${type.name}`; select.append(option); });
   if (id != null && !types.some(type => String(type.id) === String(id))) { const option = document.createElement('option'); option.value = id; option.textContent = `Баланс #${id} (недоступен)`; select.append(option); }
   const used = [...grid.querySelectorAll('select')].map(item => item.value);
   select.value = id != null ? String(id) : String(types.find(type => !used.includes(String(type.id)))?.id ?? '');
   const selection = document.createElement('span'); selection.className = 'allocation-balance-selection';
   const chip = document.createElement('span'); chip.className = 'balance-code allocation-selection-chip'; chip.setAttribute('aria-hidden', 'true');
   selection.append(chip, select); balanceLabel.append(selection);
   const hoursLabel = document.createElement('label'); hoursLabel.textContent = 'Часы';
   const input = document.createElement('input'); input.type = 'number'; input.min = '0'; input.step = 'any'; input.inputMode = 'decimal'; input.placeholder = '0'; input.value = value;
   function updateName() {
    input.name = `allocations[${i}][balances][${select.value}]`;
    const type = types.find(item => String(item.id) === select.value);
    chip.textContent = type?.code ?? '?'; chip.style.setProperty('--chip-color', type?.color || '#84939c');
   }
   select.onchange = () => { updateName(); refreshOptions(); }; updateName(); hoursLabel.append(input);
   const removeBalance = document.createElement('button'); removeBalance.type = 'button'; removeBalance.className = 'allocation-remove'; removeBalance.textContent = 'Удалить'; removeBalance.setAttribute('aria-label', 'Удалить баланс');
   removeBalance.onclick = () => { line.remove(); refreshOptions(); };
   line.append(balanceLabel, hoursLabel, removeBalance); grid.append(line); refreshOptions();
  }
  const saved = Object.entries(row.balances || {}).filter(([, value]) => value !== '' && value != null);
  if (saved.length) saved.forEach(([id, value]) => addBalanceRow(id, value));
  else if (types.length) addBalanceRow();
  addBalance.onclick = () => addBalanceRow();
  card.append(grid, addBalance); rows.append(card); refreshOptions(); updateEmpty();
 }
 function updateEmpty() { document.getElementById('allocation-empty').hidden = rows.children.length > 0; }
 function tab(name) { document.querySelectorAll('[data-tab]').forEach(b => b.classList.toggle('active', b.dataset.tab === name)); document.querySelectorAll('[data-panel]').forEach(p => p.hidden = p.dataset.panel !== name); }
 function openService(service = null) { form.reset(); rows.replaceChildren(); index = 0; tab('basic'); form.action = service ? `${createUrl}/${service.id}/update` : createUrl; const details = service ? JSON.parse(service.service_data || '{}') : {}; for (const key of ['name','price','description','folder_id']) form.elements[key].value = service?.[key] ?? (key === 'price' ? '0' : (key === 'folder_id' && form.dataset.selectedFolder !== '0' ? form.dataset.selectedFolder : '')); for (const key of ['type','code']) form.elements[key].value = details[key] ?? (key === 'type' ? 'service' : ''); form.elements.main_activity.checked = !!details.main_activity; form.elements.no_discounts.checked = !!details.no_discounts; form.elements.available.checked = !service || service.status === 'active'; (details.allocations || []).forEach(addRow); if (!rows.children.length) addRow(); serviceDialog.showModal(); }
 document.getElementById('service-add').onclick = () => openService(); document.getElementById('folder-add').onclick = () => folderDialog.showModal(); document.getElementById('allocation-add').onclick = () => addRow(); document.querySelectorAll('[data-tab]').forEach(b => b.onclick = () => tab(b.dataset.tab)); document.querySelectorAll('[data-close]').forEach(b => b.onclick = () => b.closest('dialog').close()); document.querySelectorAll('.service-edit, [data-service-edit]').forEach(b => b.onclick = () => openService(JSON.parse(b.closest('tr').dataset.service))); form.onsubmit = (event) => {
  for (const row of rows.querySelectorAll('.allocation-card')) {
   const inputs = [...row.querySelectorAll('input')], amount = inputs[0];
   if (!amount.value && inputs.slice(1).some(input => input.value !== '')) {
    event.preventDefault(); tab('balances'); amount.setCustomValidity('Укажите сумму оплаты для строки начислений.'); amount.reportValidity(); amount.setCustomValidity(''); return;
   }
  }
  form.elements.status.value = form.elements.available.checked ? 'active' : 'inactive';
 };
 const search = document.getElementById('service-search'), show = document.getElementById('show-inactive');
 const serviceRows = [...document.querySelectorAll('tr.service-item')];
 function refresh() { const query = search.value.trim().toLocaleLowerCase(); serviceRows.forEach(row => { const service = JSON.parse(row.dataset.service); row.hidden = (service.status !== 'active' && !show.checked) || (query && !service.name.toLocaleLowerCase().includes(query)); }); }
 search.oninput = refresh; show.onchange = refresh; refresh();
})();
