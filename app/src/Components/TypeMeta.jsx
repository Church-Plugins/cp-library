import Box from '@mui/material/Box';
import { Calendar, Tag } from "react-feather"
import relativeDate from "tiny-relative-date";
import { convertWPDateStringToJSDateString } from '../utils/formateDate';


export default function ItemMeta({
  date,
  items = [],
}) {
  const jsCompatibleDatestring = convertWPDateStringToJSDateString(date);

  return (
    <>
      <Box className="itemMeta__relativeReleaseDate" display="inline-flex" alignItems="center" marginRight={2}>
        <Calendar size="1em" />
        <Box component="span" marginLeft={1}>{relativeDate(jsCompatibleDatestring)}</Box>
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
		          <Box component="span">{window.cplVars.item.labelPlural}</Box>
	          ) : (
							<Box component="span">{window.cplVars.item.labelSingular}</Box>
	          ) }
          </Box>
        </Box>
      )}
    </>
  );
}
