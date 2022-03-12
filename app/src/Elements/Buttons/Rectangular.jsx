import Button from '@mui/material/Button';

export default function Rectangular({
  // See MUI docs for possible values
  variant = "solid",
  leftIcon = false,
  onClick,
  disabled = false,
  children,
	sx = { height: 55, borderRadius: 2 }
}) {
  return (
    <button
      className={`cpl-button cpl-button--${variant} cpl-button--rectangle`}
//      variant={variant}
//      startIcon={leftIcon}
//      fullWidth={fullWidth}
      onClick={onClick}
      disabled={disabled}
    >
	    {leftIcon && ( leftIcon )}
      {children}
    </button>
  );
}
