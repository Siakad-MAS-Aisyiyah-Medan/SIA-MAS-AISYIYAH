const SIZE_MAP = {
  xs: 32,
  sm: 36,
  md: 40,
  lg: 80,
  xl: 96,
};

export default function AppLogo({
  className = '',
  size = 'md',
  alt = 'Logo Aisyiyah',
  variant = 'default',
  srcUrl,
}) {
  const dimension = typeof size === 'number' ? size : SIZE_MAP[size] ?? SIZE_MAP.md;
  const wrapClass =
    variant === 'sidebar'
      ? 'app-logo-wrap app-logo-wrap--sidebar'
      : variant === 'navbar'
        ? 'app-logo-wrap app-logo-wrap--navbar'
        : 'app-logo-wrap';

  return (
    <span className={wrapClass}>
      <img
        src={srcUrl || '/logo-aisyiyah.jpg'}
        onError={(event) => {
          event.currentTarget.onerror = null;
          event.currentTarget.src = '/logo-aisyiyah.jpg';
        }}
        alt={alt}
        className={`app-logo-img ${className}`.trim()}
        style={{ width: dimension, height: dimension }}
      />
    </span>
  );
}
