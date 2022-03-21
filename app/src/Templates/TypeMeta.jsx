import Box from '@mui/material/Box';
import { Calendar, Tag } from "react-feather"
import { cplVar } from '../utils/helpers';


export default function ItemMeta({
  date,
  items = [],
}) {

  return (
    <>
      <Box className="itemMeta__relativeReleaseDate" display="inline-flex" alignItems="center" marginRight={2}>
        <Calendar size="1em" />
        <Box component="span" marginLeft={1}>{date}</Box>
      </Box>
      {items.length > 0 && (
        <Box
          className="itemMeta__categories"
          display="inline-flex"
          alignItems="center"
        >
          <Tag size="1em" />
          <Box component="span" marginLeft={1}>
	          {items.length}&nbsp;
	          {items.length > 1 ? (
		          <Box component="span">{cplVar( 'labelPlural', 'item' )}</Box>
	          ) : (
							<Box component="span">{cplVar( 'labelSingular', 'item' )}</Box>
	          ) }
          </Box>
        </Box>
      )}
    </>
  );
}
