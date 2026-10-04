const messages = document.body.dataset;
document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
document.querySelectorAll('[data-copy-target]').forEach((button) => {
  button.addEventListener('click', async () => {
    const input = document.getElementById(button.dataset.copyTarget);
    const status = button.parentElement.querySelector('[data-copy-status]');
    try {
      await navigator.clipboard.writeText(input.value);
      status.textContent = messages.copySuccess;
    } catch {
      input.focus();
      input.select();
      status.textContent = messages.copyError;
    }
  });
});
const navigation = document.querySelector(".admin-nav");
document.querySelectorAll('[data-public-image]').forEach(input => {
  const status = input.parentElement.querySelector('[data-upload-status]');
  input.addEventListener('change', () => {
    const file = input.files[0];
    const error = file && (file.size === 0 || file.size > 5 * 1024 * 1024) ? messages.uploadMax : file && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && !(file.type === '' && /\.(jpe?g|png|webp)$/i.test(file.name)) ? messages.uploadType : '';
    input.setCustomValidity(error);
    status.textContent = error || (file ? `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)` : '');
    if (error) input.reportValidity();
  });
});
const small = window.matchMedia("(max-width: 760px)");
if (navigation) {
  const update = () => { navigation.open = !small.matches; };
  update();
  small.addEventListener("change", update);
}
for (const name of ["seo_title", "seo_description", "home_seo_title", "home_seo_description"]) {
  const input = document.querySelector(`[name="${name}"]`);
  if (!input) continue;
  const output = document.createElement("output");
  output.className = "field-meta";
  output.htmlFor = input.id;
  output.id = input.id + "-count";
  input.setAttribute("aria-describedby", output.id);
  input.after(output);
  const update = () => { output.textContent = `${Array.from(input.value).length} / ${input.maxLength} ${messages.charLabel}`; };
  input.addEventListener("input", update);
  update();
}
const preview = document.querySelector("[data-seo-preview]");
if (preview) {
  const title = document.querySelector('[name="title"]');
  const seoTitle = document.querySelector('[name="seo_title"]');
  const seoDescription = document.querySelector('[name="seo_description"]');
  const short = document.querySelector('[name="short_description"]');
  const update = () => {
    preview.querySelector("[data-preview-title]").textContent = seoTitle.value.trim() || title.value.trim() + preview.dataset.suffix;
    preview.querySelector("[data-preview-description]").textContent = seoDescription.value.trim() || short.value.trim();
  };
  for (const input of [title, seoTitle, seoDescription, short]) input.addEventListener("input", update);
  update();
}
