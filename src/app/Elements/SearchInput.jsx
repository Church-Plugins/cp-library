import InputBase from '@mui/material/InputBase';
import { Search } from 'react-feather';

export default function SearchInput({
  onValueChange,
  // "short", "full"
  width = "short",
}) {
	const urlParams = new URLSearchParams(window.location.search);

  return (
    <InputBase
      className="searchInput__root"
      placeholder="Search"
      defaultValue={urlParams.get('s')}
      startAdornment={<Search />}
      onChange={e => onValueChange(e.target.value)}
    />
  );
}
