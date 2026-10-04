document.querySelectorAll('[data-gallery]').forEach(gallery => {
  const track = gallery.querySelector('.gallery-track');
  const links = [...track.querySelectorAll('[data-gallery-open]')];
  const dialog = gallery.querySelector('dialog');
  const image = dialog.querySelector('[data-dialog-image]');
  const caption = dialog.querySelector('#gallery-dialog-caption');
  const previous = gallery.querySelector('[data-gallery-prev]');
  const next = gallery.querySelector('[data-gallery-next]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0, opener = null, frame = 0;
  const position = () => links.reduce((best, link, index) => Math.abs(link.offsetLeft - track.scrollLeft) < Math.abs(links[best].offsetLeft - track.scrollLeft) ? index : best, 0);
  const update = () => {
    frame = 0;
    const first = position() + 1;
    const last = links.reduce((visible, link, index) => link.offsetLeft + link.clientWidth <= track.scrollLeft + track.clientWidth + 2 ? index + 1 : visible, first);
    gallery.querySelector('[data-gallery-position]').textContent = `${first}${last > first ? `–${last}` : ''} / ${links.length}`;
    previous.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
  };
  const move = delta => {
    const index = Math.max(0, Math.min(links.length - 1, position() + delta));
    track.scrollTo({ left: links[index].offsetLeft, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
  };
  previous.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
  track.addEventListener('scroll', () => { if (!frame) frame = requestAnimationFrame(update); }, { passive: true });
  track.addEventListener('keydown', event => {
    if (event.target !== track || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Home' || event.key === 'End') track.scrollTo({ left: event.key === 'Home' ? 0 : track.scrollWidth, behavior: 'auto' });
    else move(event.key === 'ArrowLeft' ? -1 : 1);
  });
  if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
  else window.addEventListener('resize', update);
  gallery.querySelector('.gallery-controls').hidden = links.length < 2;
  update();
  if (typeof dialog.showModal !== 'function') return;
  const show = index => {
    current = (index + links.length) % links.length;
    const link = links[current];
    gallery.querySelector('[data-gallery-error]').hidden = true;
    image.src = link.href;
    image.alt = link.querySelector('picture img').alt;
    caption.textContent = link.closest('figure').querySelector('figcaption').textContent;
    dialog.querySelector('[data-dialog-position]').textContent = `${current + 1} / ${links.length}`;
    dialog.querySelectorAll('[data-dialog-prev], [data-dialog-next]').forEach(button => { button.disabled = links.length < 2; });
  };
  links.forEach((link, index) => link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault(); opener = link; show(index);
    dialog.showModal(); document.body.classList.add('gallery-open');
  }));
  dialog.querySelector('[data-gallery-close]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-dialog-prev]').addEventListener('click', () => show(current - 1));
  dialog.querySelector('[data-dialog-next]').addEventListener('click', () => show(current + 1));
  dialog.addEventListener('keydown', event => {
    if (['ArrowLeft', 'ArrowRight'].includes(event.key)) { event.preventDefault(); show(current + (event.key === 'ArrowLeft' ? -1 : 1)); }
  });
  dialog.addEventListener('close', () => { document.body.classList.remove('gallery-open'); image.removeAttribute('src'); opener?.focus({ preventScroll: true }); });
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  image.addEventListener('error', () => { gallery.querySelector('[data-gallery-error]').hidden = false; });
});
