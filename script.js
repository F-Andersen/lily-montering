document.documentElement.classList.remove("no-js");
document.documentElement.classList.add("js");

const yearNode = document.querySelector("[data-year]");
const menuToggle = document.querySelector(".menu-toggle");
const primaryNav = document.querySelector("#primary-nav");
const leadForm = document.querySelector("[data-lead-form]");
const mobileAction = document.querySelector(".mobile-action");
const contactSection = document.querySelector("#kontakt");
const siteHeader = document.querySelector(".site-header");

if (siteHeader && "ResizeObserver" in window) {
  new ResizeObserver(() => {
    document.documentElement.style.setProperty("--header-offset", `${Math.ceil(siteHeader.getBoundingClientRect().height)}px`);
  }).observe(siteHeader);
}

if (yearNode) {
  yearNode.textContent = new Date().getFullYear();
}

function closeMenu() {
  if (!menuToggle || !primaryNav) {
    return;
  }

  menuToggle.setAttribute("aria-expanded", "false");
  menuToggle.setAttribute("aria-label", "Åpne meny");
  primaryNav.classList.remove("is-open");
  document.body.classList.remove("nav-open");
}

if (menuToggle && primaryNav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
    menuToggle.setAttribute("aria-expanded", String(!isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Åpne meny" : "Lukk meny");
    primaryNav.classList.toggle("is-open", !isOpen);
    document.body.classList.toggle("nav-open", !isOpen);
  });

  primaryNav.addEventListener("click", (event) => {
    if (event.target instanceof HTMLAnchorElement) {
      closeMenu();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });
  window.matchMedia("(max-width: 980px)").addEventListener("change", closeMenu);
}

function setFieldValidity(form, changedField = null) {
  const fields = changedField ? [changedField] : form.querySelectorAll("input, select, textarea");
  fields.forEach((field) => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
      return;
    }

    if (field.type === "hidden" || field.name === "website") {
      return;
    }

    field.setAttribute("aria-invalid", String(!field.validity.valid));
  });
}

function setFormStatus(form, message, type) {
  const status = form.querySelector(".form-status");

  if (!status) {
    return;
  }

  status.hidden = false;
  status.textContent = message;
  status.classList.toggle("is-success", type === "success");
  status.classList.toggle("is-error", type === "error");
}

function setLoading(form, isLoading) {
  const button = form.querySelector("button[type='submit']");
  const label = form.querySelector(".button-label");
  const loading = form.querySelector(".button-loading");

  form.classList.toggle("is-loading", isLoading);
  form.querySelectorAll("#photos, [data-photo-remove]").forEach(control => { control.disabled = isLoading; });

  if (button instanceof HTMLButtonElement) {
    button.disabled = isLoading;
  }

  if (label instanceof HTMLElement && loading instanceof HTMLElement) {
    label.hidden = isLoading;
    loading.hidden = !isLoading;
  }
}

if (leadForm) {
  const startedAt = leadForm.querySelector("[data-started-at]");
  const phone = leadForm.querySelector("#phone");
  const phoneCountry = leadForm.querySelector("#phone-country");
  const photoInput = leadForm.querySelector("#photos");
  const photoPreviews = leadForm.querySelector("[data-photo-previews]");
  const photoError = leadForm.querySelector("[data-photo-error]");
  const photoCount = leadForm.querySelector("#photos-count");
  let selectedPhotos = [];

  function renderPhotos() {
    if (!photoPreviews || !photoCount) return;
    photoPreviews.replaceChildren();
    photoCount.textContent = selectedPhotos.length ? `${selectedPhotos.length} av 5 bilder valgt.` : "Ingen bilder valgt.";
    selectedPhotos.forEach((photo, index) => {
      const figure = document.createElement("figure");
      figure.className = "photo-preview";
      const image = document.createElement("img");
      image.src = photo.url;
      image.alt = photo.file.name;
      const caption = document.createElement("figcaption");
      caption.textContent = photo.file.name;
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "photo-remove";
      remove.dataset.photoRemove = "";
      remove.title = `Fjern ${photo.file.name}`;
      remove.setAttribute("aria-label", remove.title);
      const icon = document.createElement("img");
      icon.src = photoInput.dataset.removeIcon;
      icon.alt = "";
      icon.width = icon.height = 18;
      remove.append(icon);
      remove.addEventListener("click", () => {
        URL.revokeObjectURL(photo.url);
        selectedPhotos.splice(index, 1);
        renderPhotos();
        photoInput.focus();
      });
      figure.append(image, caption, remove);
      photoPreviews.append(figure);
    });
  }

  if (photoInput instanceof HTMLInputElement && photoPreviews) {
    photoInput.addEventListener("change", () => {
      const files = Array.from(photoInput.files || []);
      let error = "";
      if (selectedPhotos.length + files.length > 5) error = "Du kan legge ved høyst 5 bilder.";
      else if (files.some(file => file.size > 5 * 1024 * 1024 || file.size === 0)) error = "Hvert bilde kan være høyst 5 MB og må inneholde et bilde.";
      else if (files.some(file => !["image/jpeg", "image/png", "image/webp"].includes(file.type) && !(file.type === "" && /\.(jpe?g|png|webp)$/i.test(file.name)))) error = "Velg JPEG-, PNG- eller WebP-bilder. HEIC støttes ikke.";
      photoError.textContent = error;
      photoError.hidden = error === "";
      if (!error) selectedPhotos.push(...files.map(file => ({ file, url: URL.createObjectURL(file) })));
      photoInput.value = "";
      renderPhotos();
    });
    leadForm.addEventListener("reset", () => {
      selectedPhotos.forEach(photo => URL.revokeObjectURL(photo.url));
      selectedPhotos = [];
      photoError.hidden = true;
      photoError.textContent = "";
      renderPhotos();
    });
    window.addEventListener("pagehide", event => {
      if (!event.persisted) selectedPhotos.forEach(photo => URL.revokeObjectURL(photo.url));
    });
  }

  function validatePhone() {
    if (!(phone instanceof HTMLInputElement) || !(phoneCountry instanceof HTMLSelectElement)) return;
    const raw = phone.value.trim();
    let number = raw.replace(/[() .-]/g, "");
    if (number.startsWith("00")) number = `+${number.slice(2)}`;
    let valid = /^[0-9+() .-]+$/.test(raw);
    if (!number.startsWith("+")) {
      const prefix = phoneCountry.selectedOptions[0]?.dataset.prefix || "";
      valid = valid && !!prefix && (phoneCountry.value !== "NO" || /^\d{8}$/.test(number));
      if (["SE", "FI", "UA", "DE", "GB"].includes(phoneCountry.value)) number = number.replace(/^0/, "");
      number = `+${prefix}${number}`;
    }
    valid = valid && /^\+[1-9][0-9]{6,14}$/.test(number);
    phone.setCustomValidity(raw === "" || valid ? "" : "Skriv et gyldig telefonnummer med riktig landskode.");
  }

  if (phone instanceof HTMLInputElement && phoneCountry instanceof HTMLSelectElement) {
    phone.addEventListener("input", () => {
      const international = phone.value.trim().replace(/^00/, "+").replace(/[() .-]/g, "");
      if (international.startsWith("+")) {
        const match = Array.from(phoneCountry.options).find(option => option.dataset.prefix && international.startsWith(`+${option.dataset.prefix}`));
        phoneCountry.value = match?.value || "OTHER";
      }
      validatePhone();
    });
    phoneCountry.addEventListener("change", () => {
      validatePhone();
      if (phone.hasAttribute("aria-invalid")) setFieldValidity(leadForm, phone);
    });
    leadForm.addEventListener("reset", () => phone.setCustomValidity(""));
  }

  if (startedAt instanceof HTMLInputElement) {
    startedAt.value = String(Date.now());
  }

  leadForm.addEventListener("input", (event) => setFieldValidity(leadForm, event.target));
  leadForm.addEventListener("change", (event) => setFieldValidity(leadForm, event.target));

  leadForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    validatePhone();
    setFieldValidity(leadForm);

    if (!leadForm.checkValidity()) {
      leadForm.reportValidity();
      setFormStatus(leadForm, "Fyll ut de obligatoriske feltene før du sender.", "error");
      return;
    }

    const formData = new FormData(leadForm);
    if (photoInput) {
      formData.delete("photos[]");
      selectedPhotos.forEach(photo => formData.append("photos[]", photo.file, photo.file.name));
    }

    setLoading(leadForm, true);
    setFormStatus(leadForm, "Sender forespørselen...", "success");

    try {
      leadForm.querySelectorAll(".field-error:not([data-photo-error])").forEach((node) => node.remove());
      if (photoError) { photoError.hidden = true; photoError.textContent = ""; }
      leadForm.querySelectorAll("[aria-describedby^='error-']").forEach((field) => {
        if (field.id === "photos") field.setAttribute("aria-describedby", "photos-note photos-count");
        else field.removeAttribute("aria-describedby");
      });
      const response = await fetch(leadForm.action, {
        method: "POST",
        headers: {
          "Accept": "application/json",
        },
        body: formData,
      });

      const payload = await response.json().catch(() => null);

      if (!response.ok || !payload || payload.ok !== true) {
        Object.entries(payload?.errors || {}).forEach(([name, message]) => {
          const field = name === "photos" ? photoInput : leadForm.elements.namedItem(name);
          if (!(field instanceof HTMLElement)) return;
          const errorNode = document.createElement("span");
          errorNode.id = `error-${name}`;
          errorNode.className = "field-error";
          errorNode.textContent = message;
          field.setAttribute("aria-invalid", "true");
          field.setAttribute("aria-describedby", errorNode.id);
          field.closest(".form-row, .consent-row")?.append(errorNode);
        });
        leadForm.querySelector("[aria-invalid='true']")?.focus();
        throw new Error(payload?.message || (response.status === 413 ? "Forespørselen er for stor. Velg høyst 5 bilder på inntil 5 MB hver." : "Kunne ikke sende forespørselen akkurat nå."));
      }

      setFormStatus(leadForm, payload.message || "Takk. Forespørselen er sendt.", "success");
      leadForm.reset();
      leadForm.querySelectorAll("[aria-invalid]").forEach((field) => field.removeAttribute("aria-invalid"));

      if (startedAt instanceof HTMLInputElement) {
        startedAt.value = String(Date.now());
      }
    } catch (error) {
      setFormStatus(
        leadForm,
        error instanceof TypeError ? "Forbindelsen ble brutt. Kontroller tilkoblingen og prøv igjen." : error instanceof Error ? error.message : "Kunne ikke sende forespørselen akkurat nå.",
        "error",
      );
    } finally {
      setLoading(leadForm, false);
    }
  });
}

if (mobileAction && contactSection && "IntersectionObserver" in window) {
  const observer = new IntersectionObserver(
    ([entry]) => {
      mobileAction.classList.toggle("is-hidden", entry.isIntersecting);
    },
    { threshold: 0 },
  );

  observer.observe(contactSection);
}
