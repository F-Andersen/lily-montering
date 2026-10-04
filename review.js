(() => {
  const redeem = () => {
    const fragment = new URLSearchParams(window.location.hash.slice(1));
    const token = fragment.get('token');
    if (!token) return;
    // Remove the invitation from browser history before it is exchanged for a session.
    window.history.replaceState(null, '', window.location.pathname);
    const form = document.getElementById('review-fragment');
    if (form) {
      form.elements.invitation.value = token.slice(0, 512);
      form.requestSubmit();
    }
  };
  window.addEventListener('hashchange', redeem);
  redeem();
})();
