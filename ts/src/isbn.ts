/**
 * ISBN-10 / ISBN-13 validation and normalization.
 *
 * Biblionet only matches ISBN-13 in its search, so everything is normalized
 * to a bare 13-digit string.
 */

const ISBN10 = /^[0-9]{9}[0-9X]$/;
const ISBN13 = /^[0-9]{13}$/;
// Same characters as PHP trim(), so all implementations reject e.g. a leading no-break space.
const TRIM_CHARS = " \t\n\r\0\x0B";

/**
 * ISBN-13 (digits only) for a valid ISBN-10 or ISBN-13, else null.
 * Only spaces and hyphens are tolerated as separators; any other character
 * makes the input invalid.
 */
export function normalizeIsbn(value: string): string | null {
  const isbn = phpTrim(value).replaceAll(" ", "").replaceAll("-", "").toUpperCase();
  if (ISBN10.test(isbn)) {
    return isValid10(isbn) ? tenToThirteen(isbn) : null;
  }
  if (ISBN13.test(isbn)) {
    return isValid13(isbn) ? isbn : null;
  }
  return null;
}

export function isValid10(isbn: string): boolean {
  if (!ISBN10.test(isbn)) {
    return false;
  }
  let sum = 0;
  for (let i = 0; i < 10; i++) {
    const c = isbn.charAt(i);
    sum += (10 - i) * (c === "X" ? 10 : Number(c));
  }
  return sum % 11 === 0;
}

export function isValid13(isbn: string): boolean {
  return ISBN13.test(isbn) && checkDigit13(isbn.slice(0, 12)) === isbn.charAt(12);
}

export function tenToThirteen(isbn10: string): string {
  const body = "978" + isbn10.slice(0, 9);
  return body + checkDigit13(body);
}

function phpTrim(value: string): string {
  let start = 0;
  let end = value.length;
  while (start < end && TRIM_CHARS.includes(value.charAt(start))) start++;
  while (end > start && TRIM_CHARS.includes(value.charAt(end - 1))) end--;
  return value.slice(start, end);
}

function checkDigit13(first12: string): string {
  let sum = 0;
  for (let i = 0; i < 12; i++) {
    sum += Number(first12.charAt(i)) * (i % 2 === 0 ? 1 : 3);
  }
  return String((10 - (sum % 10)) % 10);
}
