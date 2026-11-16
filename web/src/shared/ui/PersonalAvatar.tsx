const DEFAULT_AVATAR = `${import.meta.env.BASE_URL}avatar-default.svg`

function fotoPublicUrl(fotoRuta: string): string {
  const path = fotoRuta.replace(/^\/+/, '')
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  return `/bioseguridad/api/public/${path}`
}

type Props = {
  fotoRuta?: string | null
  alt?: string
  size?: number
  className?: string
}

export function PersonalAvatar({ fotoRuta, alt = '', size = 160, className = '' }: Props) {
  const src = fotoRuta?.trim() ? fotoPublicUrl(fotoRuta) : DEFAULT_AVATAR
  return (
    <img
      src={src}
      alt={alt}
      width={size}
      height={size}
      className={className || 'rounded-md object-cover'}
      style={{ width: size, height: size }}
      onError={(e) => {
        const img = e.currentTarget
        if (img.src !== DEFAULT_AVATAR) {
          img.src = DEFAULT_AVATAR
        }
      }}
    />
  )
}

export function personalAvatarSrc(fotoRuta?: string | null): string {
  return fotoRuta?.trim() ? fotoPublicUrl(fotoRuta) : DEFAULT_AVATAR
}
