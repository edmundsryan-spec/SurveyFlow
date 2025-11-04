document.addEventListener('click', function (e) {
  const el = e.target.closest('.sf-copy-shortcode');
  if (!el) return;
  const code = el.getAttribute('data-code');
  if (!code) return;
  navigator.clipboard.writeText(code).then(() => {
    el.classList.add('sf-copied');
    const old = el.innerHTML;
    el.innerHTML = code + ' ✅';
    setTimeout(() => {
      el.innerHTML = old;
      el.classList.remove('sf-copied');
    }, 1200);
  });
});