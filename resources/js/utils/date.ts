/** Calendar dates are not instants: never shift them through UTC. */
export function localDate(): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(new Date());
  const get = (type: string) => parts.find(part => part.type === type)?.value;
  return `${get('year')}-${get('month')}-${get('day')}`;
}

export function calendarDate(value: string): string {
  return /^\d{4}-\d{2}-\d{2}/.test(value) ? value.substring(0, 10) : '';
}
