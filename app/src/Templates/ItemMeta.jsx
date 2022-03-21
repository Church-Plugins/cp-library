import Box from '@mui/material/Box';
import { CalendarTodayOutlined, SellOutlined } from "@mui/icons-material"

export default function ItemMeta({
  date,
  category = [],
}) {

  return (
    <>
      <Box className="itemMeta__relativeReleaseDate" display="inline-flex" alignItems="center" marginRight={2}>
        <CalendarTodayOutlined size="1em" />
        <Box component="span" marginLeft={1}>{date}</Box>
      </Box>
      {category.length > 0 && (
        <Box
          className="itemMeta__categories"
          display="inline-flex"
          alignItems="center"
        >
          <SellOutlined size="1em" />
          <Box component="span" marginLeft={1}>{category.join(",")}</Box>
        </Box>
      )}
    </>
  );
}
