export function currentAcademicYear(date = new Date()) {
  const year = date.getMonth() >= 6 ? date.getFullYear() : date.getFullYear() - 1;
  return `${year}/${year + 1}`;
}

export function currentSemester(date = new Date()) {
  return date.getMonth() >= 6 ? 'Ganjil' : 'Genap';
}
