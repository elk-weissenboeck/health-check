export const TextUtils = {
  norm(s) {
    return String(s || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")   // diakritische Zeichen entfernen
      .replace(/\s+/g, " ")
      .trim();
  }
};
