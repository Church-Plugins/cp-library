import InputBase from '@mui/material/InputBase';
import { Search } from 'react-feather';

export default function SearchInput({
  onValueChange,
  // "short", "full"
  width = "short",
}) {
  return (
    <InputBase
      className="searchInput__root"
      placeholder="Search"
      sx={{ width: width === "short" ? 250 : "100%" }}
      startAdornment={<Search />}
      onChange={e => onValueChange(e.target.value)}
    />
  );
}
