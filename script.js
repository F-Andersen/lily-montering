const yearNode = document.querySelector("[data-year]");

if (yearNode) {
  yearNode.textContent = new Date().getFullYear();
}

const leadForm = document.querySelector("[data-lead-form]");
const mobileAction = document.querySelector(".mobile-action");
const contactSection = document.querySelector("#kontakt");

if (leadForm) {
  leadForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const status = leadForm.querySelector(".form-status");

    if (!status) {
      return;
    }

    status.hidden = false;
    status.textContent =
      "Skjemaet er validert i nettleseren. Koble det til e-post eller skjemaløsning før publisering for å motta forespørsler.";
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
