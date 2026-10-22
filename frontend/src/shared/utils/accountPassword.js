export function generateAccountPassword() {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  return `Aa1!${Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('').slice(0, 12)}`;
}
