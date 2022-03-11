import { Button, ClickAwayListener, Popper } from "@mui/material";
import { Box } from "@mui/system";
import Collapse from "@mui/material/Collapse"
import { useState } from "react";
import { ChevronDown, ChevronUp } from "react-feather";
import clsx from "clsx";

import { noop } from "../../utils/noop";
import PopularTopics from "./PopularTopics";
import AllTopics from "./AllTopics";

export default function TopicFilter({
  activeFilters = [],
  onFilterChange = noop,
}) {
  const [anchorEl, setAnchorEl] = useState(null);
  const [isOpen, setIsOpen] = useState(false);
  const [isViewingAll, setIsViewingAll] = useState(false);

  const handleButtonClick = e => {
    setAnchorEl(e.currentTarget);
    setIsOpen(!isOpen);
  };

  const handleClose = () => {
    setIsOpen(false);
  };

  return (
    <ClickAwayListener onClickAway={handleClose}>
      <Box className={clsx("topic__root", isOpen && "topic__active")}>
        <Button
          className="topic__toggleButton"
          variant="text"
          endIcon={isOpen ? <ChevronUp /> : <ChevronDown />}
          onClick={handleButtonClick}
          sx={{
            whiteSpace: "nowrap",
            backgroundColor: isOpen && "#E5E5E5",
            borderBottomLeftRadius: 0,
            borderBottomRightRadius: 0,
          }}
          disableRipple
        >
          Popular Topics
        </Button>

        <Popper
          className="topic__popper"
          open={isOpen}
          anchorEl={anchorEl}
          keepMounted={true}
          style={{ width: isViewingAll ? "100%" : 281, maxWidth: "100vw", boxSizing: "border-box" }}
          placement={isViewingAll ? "bottom" : "bottom-start"}
          transition
        >
          {({ TransitionProps }) => (
            <Collapse {...TransitionProps} timeout={150}>
              <Box
                className="topic__popperContent"
                backgroundColor="white"
                paddingY={1}
                paddingX={2}
                borderRadius={2}
                sx={{ borderTopLeftRadius: isViewingAll ? 8 : 0 }}
              >
              {isViewingAll ? (
                <AllTopics
                  onBack={() => setIsViewingAll(false)}
                  activeFilters={activeFilters}
                  onFilterChange={onFilterChange}
                />
              ) : (
                <PopularTopics
                  onViewAll={() => setIsViewingAll(true)}
                  activeFilters={activeFilters}
                  onFilterChange={onFilterChange}
                />
              )}
              </Box>
            </Collapse>
          )}
        </Popper>
      </Box>
    </ClickAwayListener>
  );
}
