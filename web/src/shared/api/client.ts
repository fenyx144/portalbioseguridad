const API_BASE =
  import.meta.env.VITE_API_URL ?? '/bioseguridad/api/public'

export type ApiError = {
  code: string
  message: string
  warnings?: string[]
  fields?: unknown
}

type ApiEnvelope<T> = {
  ok: boolean
  data: T
  error: ApiError | null
}

export class ApiClientError extends Error {
  code: string
  status: number
  payload?: ApiError

  constructor(message: string, code: string, status: number, payload?: ApiError) {
    super(message)
    this.code = code
    this.status = status
    this.payload = payload
  }
}

async function parse<T>(res: Response): Promise<T> {
  const json = (await res.json()) as ApiEnvelope<T>
  if (!json.ok) {
    throw new ApiClientError(
      json.error?.message ?? 'Error',
      json.error?.code ?? 'error',
      res.status,
      json.error ?? undefined,
    )
  }
  return json.data
}

/** URL para abrir/descargar PDF (misma sesión que el resto de la API). */
export function apiPdfUrl(path: string, params?: Record<string, string | number | undefined>): string {
  const url = new URL(API_BASE + path, window.location.origin)
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== '') url.searchParams.set(k, String(v))
    })
  }
  return url.toString()
}

export type OpenPdfOptions = {
  /** Abre el visor embebido con la URL del PDF (sin pestaña nueva ni descarga previa). */
  onInlinePreview: (pdfUrl: string) => void
}

/** Muestra el PDF en el visor de la app (iframe → API con cookies de sesión). */
export function openPdf(
  path: string,
  params?: Record<string, string | number | undefined>,
  options?: OpenPdfOptions,
): void {
  const url = apiPdfUrl(path, params)
  if (!options?.onInlinePreview) {
    throw new Error('Vista previa no configurada.')
  }
  options.onInlinePreview(url)
}

export const api = {
  async get<T>(path: string, params?: Record<string, string | number | undefined>): Promise<T> {
    const url = new URL(API_BASE + path, window.location.origin)
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        if (v !== undefined && v !== '') url.searchParams.set(k, String(v))
      })
    }
    const res = await fetch(url.toString(), { credentials: 'include' })
    return parse<T>(res)
  },

  async post<T>(path: string, body?: unknown): Promise<T> {
    const res = await fetch(API_BASE + path, {
      method: 'POST',
      credentials: 'include',
      headers: body instanceof FormData ? undefined : { 'Content-Type': 'application/json' },
      body: body instanceof FormData ? body : JSON.stringify(body ?? {}),
    })
    return parse<T>(res)
  },

  async patch<T>(path: string, body: unknown): Promise<T> {
    const res = await fetch(API_BASE + path, {
      method: 'PATCH',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
    return parse<T>(res)
  },

  async del<T>(path: string): Promise<T> {
    const res = await fetch(API_BASE + path, { method: 'DELETE', credentials: 'include' })
    return parse<T>(res)
  },
}
