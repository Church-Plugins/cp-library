import Button from "@mui/material/Button";
import FormGroup from "@mui/material/FormGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import Checkbox from "@mui/material/Checkbox";
import { ArrowRightCircle } from "react-feather";

import { noop } from "../../utils/noop";
import Label from "./Label";

const popularTopicsValueByLabel = {
  worry: "Worry",
  doubt: "Doubt",
  fear: "Fear",
  encouragement: "Encouragement",
};

export default function PopularTopics({
  onViewAll = noop,
  activeFilters = [],
  onFilterChange = noop,
}) {
  return (
    <>
      <Button
        className="topic__viewAllButton"
        variant="text"
        fullWidth
        endIcon={<ArrowRightCircle />}
        sx={{ justifyContent: "space-between" }}
        onClick={onViewAll}
        disableRipple
      >
        View All
      </Button>
      <FormGroup className="topic__itemList">
        {Object.entries(popularTopicsValueByLabel).map(([ value, label ]) => (
          <FormControlLabel
            key={value}
            className="topic__item"
            control={
              <Checkbox
                className="topic__itemCheckbox"
                value={value}
                onChange={() => onFilterChange(value)}
                disableRipple
              />
            }
            label={<Label>{label}</Label>}
            checked={activeFilters.includes(value)}
          />
        ))}
      </FormGroup>
    </>
  );
}
