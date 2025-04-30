document.documentElement.classList.add('no-transition');
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('wccc-toggle-darkmode');
  if (!toggle) return;

  const body = document.body;
  const icon = toggle.querySelector('span');

  // – helper
  const setDark = on => {
    body.classList.toggle('wccc-dark', on);
    if (icon) icon.textContent = on ? '☀️ Light Mode' : '🌙 Dark Mode';
  };

  // 1. Initialer Zustand aus localStorage
  let persisted      = localStorage.getItem('wccc_darkmode') === 'on';
  setDark(persisted);
  setTimeout(() => document.documentElement.classList.remove('no-transition'), 50);

  // 2. Klick ⇒ persistent umschalten und speichern
  toggle.addEventListener('click', () => {
    persisted = !persisted;                          // neuen Dauer-Zustand merken
    localStorage.setItem('wccc_darkmode', persisted ? 'on' : 'off');
    setDark(persisted);
  });

  // 3. Hover-Preview ⇒ zeigt gegenteiliges Theme, speichert NICHT
  toggle.addEventListener('mouseenter', () => {
    setDark(!persisted);                             // Vorschau
  });
  toggle.addEventListener('mouseleave', () => {
    setDark(persisted);                              // zurück zum Dauer-Zustand
  });
});
