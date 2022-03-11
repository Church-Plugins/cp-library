import Button from '@mui/material/Button';

export default function Rectangular({
  // See MUI docs for possible values
  variant = "contained",
  leftIcon,
  fullWidth = false,
  onClick,
  disabled = false,
  children,
	sx = { height: 55, borderRadius: 2 }
}) {
  return (
    <Button
      className={`cpl-button cpl-button--${variant} rectangularButton__root rectangularButton__${variant}`}
      variant={variant}
      startIcon={leftIcon}
      fullWidth={fullWidth}
      onClick={onClick}
      disabled={disabled}
    >
      {children}
    </Button>
  );
}
