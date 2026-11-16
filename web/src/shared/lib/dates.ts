/** Fecha local (no UTC) en formato YYYY-MM-DD. */
export function toIsoLocal(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function todayIso(): string {
  return toIsoLocal(new Date())
}

export function startOfWeekMonday(d: Date): string {
  const x = new Date(d)
  const day = x.getDay()
  const diff = day === 0 ? -6 : 1 - day
  x.setDate(x.getDate() + diff)
  return toIsoLocal(x)
}

export function addDays(iso: string, n: number): string {
  const x = new Date(iso + 'T12:00:00')
  x.setDate(x.getDate() + n)
  return toIsoLocal(x)
}

/** YYYY-MM-DD → dd/mm/yyyy (formato único para el usuario). */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso))
  return m ? `${m[3]}/${m[2]}/${m[1]}` : String(iso)
}

/** "Lun 09/11/2026" */
export function formatShortDate(iso: string): string {
  if (!iso) return '—'
  return `${dayShort(iso.slice(0, 10))} ${formatDate(iso)}`
}

const LETRAS = ['D', 'L', 'M', 'X', 'J', 'V', 'S']
const CORTOS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']

/** Letra del día: L M X J V S D */
export function dayLetter(iso: string): string {
  return LETRAS[new Date(iso + 'T12:00:00').getDay()]
}

export function dayShort(iso: string): string {
  return CORTOS[new Date(iso + 'T12:00:00').getDay()]
}

export function dayNum(iso: string): string {
  return iso.slice(8, 10)
}

export function weekDaysFromMonday(semanaInicio: string): string[] {
  return Array.from({ length: 7 }, (_, i) => addDays(semanaInicio, i))
}

export function formatWeekRange(semanaInicio: string): string {
  const fin = addDays(semanaInicio, 6)
  return `Desde ${formatShortDate(semanaInicio)} – Hasta ${formatShortDate(fin)}`
}

/** "09/11/2026 – 15/11/2026" */
export function formatWeekCompact(semanaInicio: string): string {
  return `${formatDate(semanaInicio)} – ${formatDate(addDays(semanaInicio, 6))}`
}
