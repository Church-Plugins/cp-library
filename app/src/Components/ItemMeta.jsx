import Box from '@mui/material/Box';
import { Calendar, Tag } from "react-feather"
import relativeDate from "tiny-relative-date";


export default function ItemMeta({
  date,
  category,
}) {
  return (
    <>
      <Box className="itemMeta__relativeReleaseDate" display="inline-flex" alignItems="center">
        <Calendar />
        <Box component="span" marginLeft={1}>{relativeDate(date)}</Box>
      </Box>
      {category && category.length > 0 && (
        <Box
          className="itemMeta__categories"
          display="inline-flex"
          alignItems="center"
          marginLeft={2}
        >
          <Tag />
          <Box component="span" marginLeft={1}>{category.join(",")}</Box>
        </Box>
      )}
    </>
  );
}
