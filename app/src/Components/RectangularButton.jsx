import Button from '@mui/material/Button';

export default function RectangularButton({
  // See MUI docs for possible values
  variant = "contained",
  leftIcon,
  fullWidth = false,
  onClick,
  disabled = false,
  children,
}) {
  return (
    <Button
      className={`rectangularButton__root rectangularButton__${variant}`}
      variant={variant}
      startIcon={leftIcon}
      fullWidth={fullWidth}
      onClick={onClick}
      disabled={disabled}
      sx={{ height: 55, borderRadius: 2 }}
    >
      {children}
    </Button>
  );
}
