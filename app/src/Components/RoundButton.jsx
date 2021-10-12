import Button from '@mui/material/Button';

export default function RoundButton({
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
      className={`roundButton__root roundButton__${variant}`}
      variant={variant}
      startIcon={leftIcon}
      fullWidth={fullWidth}
      onClick={onClick}
      disabled={disabled}
      sx={{ borderRadius: 100 }}
    >
      {children}
    </Button>
  );
}
