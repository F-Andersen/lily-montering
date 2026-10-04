document.querySelectorAll('[data-gallery]').forEach(gallery => {
  const track = gallery.querySelector('.gallery-track');
  const links = [...track.querySelectorAll('[data-gallery-open]')];
  const pages = [...track.querySelectorAll('[data-gallery-page]')];
  const dialog = gallery.querySelector('dialog');
  const image = dialog.querySelector('[data-dialog-image]');
  const caption = dialog.querySelector('#gallery-dialog-caption');
  const previous = gallery.querySelector('[data-gallery-prev]');
  const next = gallery.querySelector('[data-gallery-next]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0, opener = null, frame = 0;
  const mobile = window.matchMedia('(max-width: 680px)');
  // Mobile advances individual photos; desktop advances four-photo mosaics.
  const slides = () => mobile.matches ? links : pages;
  const left = slide => slide.getBoundingClientRect().left - track.getBoundingClientRect().left + track.scrollLeft;
  const position = () => slides().reduce((best, slide, index, all) => Math.abs(left(slide) - track.scrollLeft) < Math.abs(left(all[best]) - track.scrollLeft) ? index : best, 0);
  const update = () => {
    frame = 0;
    const first = mobile.matches ? position() + 1 : position() * 4 + 1;
    const last = mobile.matches ? first : Math.min(first + 3, links.length);
    gallery.querySelector('[data-gallery-position]').textContent = `${first}${last > first ? `–${last}` : ''} / ${links.length}`;
    previous.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
  };
  const move = delta => {
    const all = slides();
    const index = Math.max(0, Math.min(all.length - 1, position() + delta));
    track.scrollTo({ left: left(all[index]), behavior: reduceMotion.matches ? 'auto' : 'smooth' });
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
  mobile.addEventListener('change', () => { track.scrollTo({ left: 0, behavior: 'auto' }); update(); });
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
