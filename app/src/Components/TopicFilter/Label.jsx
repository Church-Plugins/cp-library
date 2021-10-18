import Typography from "@mui/material/Typography";

export default function Label(props) {
  return <Typography fontWeight="lighter" {...props}>{props.children}</Typography>
}
