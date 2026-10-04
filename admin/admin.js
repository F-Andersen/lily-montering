document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
const navigation = document.querySelector(".admin-nav");
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
  const update = () => { output.textContent = `${Array.from(input.value).length} / ${input.maxLength} tegn`; };
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
