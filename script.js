document.documentElement.classList.remove("no-js");
document.documentElement.classList.add("js");

const yearNode = document.querySelector("[data-year]");
const menuToggle = document.querySelector(".menu-toggle");
const primaryNav = document.querySelector("#primary-nav");
const leadForm = document.querySelector("[data-lead-form]");
const mobileAction = document.querySelector(".mobile-action");
const contactSection = document.querySelector("#kontakt");

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
}

function setFieldValidity(form) {
  form.querySelectorAll("input, select, textarea").forEach((field) => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
      return;
    }

    if (field.type === "hidden" || field.name === "website") {
      return;
    }

    field.toggleAttribute("aria-invalid", !field.validity.valid);
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

  if (startedAt instanceof HTMLInputElement) {
    startedAt.value = String(Date.now());
  }

  leadForm.addEventListener("input", () => setFieldValidity(leadForm));
  leadForm.addEventListener("change", () => setFieldValidity(leadForm));

  leadForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    setFieldValidity(leadForm);

    if (!leadForm.checkValidity()) {
      leadForm.reportValidity();
      setFormStatus(leadForm, "Fyll ut de obligatoriske feltene før du sender.", "error");
      return;
    }

    const formData = new FormData(leadForm);
    const body = new URLSearchParams();

    formData.forEach((value, key) => {
      body.append(key, String(value));
    });

    setLoading(leadForm, true);
    setFormStatus(leadForm, "Sender forespørselen...", "success");

    try {
      const response = await fetch(leadForm.action, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        },
        body,
      });

      const payload = await response.json().catch(() => null);

      if (!response.ok || !payload || payload.ok !== true) {
        throw new Error(payload?.message || "Kunne ikke sende forespørselen akkurat nå.");
      }

      setFormStatus(leadForm, payload.message || "Takk. Forespørselen er sendt.", "success");
      leadForm.reset();

      if (startedAt instanceof HTMLInputElement) {
        startedAt.value = String(Date.now());
      }
    } catch (error) {
      setFormStatus(
        leadForm,
        error instanceof Error ? error.message : "Kunne ikke sende forespørselen akkurat nå.",
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
    { threshold: 0.08 },
  );

  observer.observe(contactSection);
}
