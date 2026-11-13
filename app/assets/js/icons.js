/* Ikon stroke 16×16 — path sama dengan versi FigCraft (desain/icons.py). */
export const ICONS = {
  dashboard:   'M2 2H7V7H2ZM9 2H14V7H9ZM2 9H7V14H2ZM9 9H14V14H9Z',
  summary:     'M5.2 3H14.5M5.2 8H14.5M5.2 13H10.5M1.6 2.2L2.7 3.3L4.3 1.5M1.6 7.2L2.7 8.3L4.3 6.5',
  identity:    'M3 14V3.5L8 1.5L13 3.5V14M6 14V9.5H10V14M6 5H7M9 5H10M6 7H7M9 7H10',
  period:      'M2 4.5H14V14H2ZM2 8H14M5 2V5M11 2V5',
  lecturer:    'M8 7.5A2.5 2.5 0 1 0 8 2.5A2.5 2.5 0 1 0 8 7.5ZM2.5 14C2.5 10.8 4.5 9 8 9C11.5 9 13.5 10.8 13.5 14',
  finance:     'M2 5.5H12.5A1.5 1.5 0 0 1 14 7V12.5A1.5 1.5 0 0 1 12.5 14H3.5A1.5 1.5 0 0 1 2 12.5ZM2 5.5V3.5H10.5M11 9.6H13.2',
  partner:     'M6.5 9.5L9.5 6.5M6 4.5H4.5A3 3 0 0 0 4.5 10.5H6M10 4.5H11.5A3 3 0 0 1 11.5 10.5H10',
  access:      'M8 3.5A4.5 4.5 0 0 1 12.5 8M8 3.5A4.5 4.5 0 0 0 3.5 8M2.5 8H13.5V14H2.5ZM8 10.5V11.5M5.5 3.2A4 4 0 0 1 10.5 3.2',
  student:     'M1 5.5L8 2L15 5.5L8 9ZM4 7.3V10.5C4 11.9 5.8 13 8 13C10.2 13 12 11.9 12 10.5V7.3',
  curriculum:  'M3 1.5H13V14.5H3ZM3 1.5V14.5M5.5 5H11M5.5 8H11M5.5 11H9',
  facility:    'M6.5 1.5H9.5M7 1.5V6.5L3 13H13L9 6.5V1.5M4.6 10H11.4',
  library:     'M8 4.5C6.5 3.2 4.5 3 2 3.2V12.5C4.5 12.3 6.5 12.5 8 13.8C9.5 12.5 11.5 12.3 14 12.5V3.2C11.5 3 9.5 3.2 8 4.5M8 4.5V13.8',
  competency:  'M4 3.5H3A1.5 1.5 0 0 0 1.5 5V13A1.5 1.5 0 0 0 3 14.5H13A1.5 1.5 0 0 0 14.5 13V5A1.5 1.5 0 0 0 13 3.5H12M4 3.5V2A1 1 0 0 1 5 1H11A1 1 0 0 1 12 2V3.5H4ZM5.2 9L7 10.8L11 6.8',
  survey:      'M2.5 14V9M8 14V4M13.5 14V7M1 14H15',
  archive:     'M1.5 3H14.5V6H1.5ZM2.8 6H13.2V13.5H2.8ZM6.5 8.5H9.5',
  document:    'M3.5 1.5H9.5L12.5 4.5V14.5H3.5ZM9.5 1.5V4.5H12.5M6 7.5H10M6 10.5H10',
  consistency: 'M8 1.5L13.5 3.5V8C13.5 11 11 13 8 14.5C5 13 2.5 11 2.5 8V3.5ZM5.8 7.8L7.4 9.4L10.6 6.2',
};

export function icon(name, size = 16) {
  const d = ICONS[name];
  if (!d) return '';
  return `<svg class="ico" width="${size}" height="${size}" viewBox="0 0 16 16"
    fill="none" stroke="currentColor" stroke-width="1.35"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="${d}"/></svg>`;
}
