import Box from '@mui/material/Box';
import { Calendar, Tag } from "react-feather"
import relativeDate from "tiny-relative-date";


export default function ItemMeta({
  date,
  category = [],
}) {
  return (
    <>
      <Box className="itemMeta__relativeReleaseDate" display="inline-flex" alignItems="center" marginRight={2}>
        <Calendar size="1em" />
        <Box component="span" marginLeft={1}>{relativeDate(date)}</Box>
      </Box>
      {category.length > 0 && (
        <Box
          className="itemMeta__categories"
          display="inline-flex"
          alignItems="center"
        >
          <Tag size="1em" />
          <Box component="span" marginLeft={1}>{category.join(",")}</Box>
        </Box>
      )}
    </>
  );
}
