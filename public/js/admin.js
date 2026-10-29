const sidebar = document.getElementById('sidebar');
const menuBtn = document.getElementById('menuBtn');

// buka/tutup sidebar di layar kecil
menuBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  sidebar.classList.toggle('open');
});

// klik di luar sidebar = tutup
document.addEventListener('click', (e) => {
  if (!sidebar.contains(e.target)) {
    sidebar.classList.remove('open');
  }
});