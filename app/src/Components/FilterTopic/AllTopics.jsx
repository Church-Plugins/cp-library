import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import FormControlLabel from "@mui/material/FormControlLabel";
import Grid from "@mui/material/Grid";
import Checkbox from "@mui/material/Checkbox";
import Box from "@mui/system/Box";
import { useRef, useState, useEffect, createRef, Fragment } from "react";
import { ArrowLeftCircle } from "react-feather";

import Controllers_WP_REST_Request from '../../Controllers/WP_REST_Request';
import { noop } from "../../utils/noop";

import LoadingIndicator from "../../Elements/LoadingIndicator";
import ErrorDisplay from "../../Elements/ErrorDisplay";
import Label from "./Label";

const alphabet = [
  'A', 'B', 'C', 'D', 'E', 'F', 'G',
  'H', 'I', 'J', 'K', 'L', 'M', 'N',
  'O', 'P', 'Q', 'R', 'S', 'T', 'U',
  'V', 'W', 'X', 'Y', 'Z'
];

export default function AllTopics({
  onBack = noop,
  activeFilters = [],
  onFilterChange = noop,
}) {
  const [topicsFullItems, setTopicsFullItems] = useState([]);
	const [topicsFullLoading, setTopicsFullLoading] = useState(false);
	const [topicsFullError, setTopicsFullError] = useState();
  const [selectedLetter, setSelectedLetter] = useState();
  const scrollingContainerEl = useRef();
  const filterHeaderEls = useRef(alphabet.reduce((allRefs, currentLetter) => ({
    ...allRefs,
    [currentLetter]: createRef(),
  }), {}));

  useEffect(() => {
		(async () => {
      try {
        setTopicsFullLoading( true );
        const restRequest = new Controllers_WP_REST_Request();
        const response = await restRequest.get( {endpoint: 'items/dictionary', params: null} );
				setTopicsFullItems(response.items);
			} catch ( error ) {
				setTopicsFullError(error);
			} finally {
				setTopicsFullLoading(false);
			}
		})();
  // Do we want to call this everytime the active filter changes?
	}, []);

  const handleLetterClick = letter => {
    const filterHeader = filterHeaderEls.current[letter].current;

    if (filterHeader === null || scrollingContainerEl.current === null) return;

    setSelectedLetter(letter);

    scrollingContainerEl.current.scrollTo({
      top: filterHeader.offsetTop,
      behavior: "smooth"
    });
  }

  return (
    <>
      <Box display="flex">
        <Button
          className="topic__backButton"
          variant="text"
          startIcon={<ArrowLeftCircle />}
          onClick={onBack}
          disableRipple
        >
          Back
        </Button>
        <Box className="topic__letterNavContainer" flex={1} display="flex" justifyContent="space-around">
          {alphabet.map(letter => (
            <Fragment key={letter}>
              <Button
                className="topic__letterNavItem"
                variant="text"
                sx={{ minWidth: "unset" }}
                onClick={() => handleLetterClick(letter)}
                disableRipple
              >
                {letter === selectedLetter ? (
                  <b>{letter.toUpperCase()}</b>
                ) : letter.toUpperCase()}
              </Button>
              <Divider
                className="topic__letterNavDivider"
                orientation="vertical"
                variant="middle"
                flexItem
              />
            </Fragment>
          ))}
        </Box>
      </Box>

      {topicsFullLoading ? (
        <LoadingIndicator />
      ) : topicsFullError ? (
        <ErrorDisplay error={topicsFullError} />
      ) : (
        <Box
          className="topic__scrollingContainer"
          ref={scrollingContainerEl}
          maxHeight={356}
          overflow="auto"
          // Needed to make this the offsetParent element for letter nav scrolling
          position="relative"
        >
          {Object.entries(topicsFullItems).map(([ letter, filters ]) => (
            <Box key={letter} ref={filterHeaderEls.current[letter.toUpperCase()]}>
              <p className="topic__header">{letter.toUpperCase()}</p>
              <Grid container className="topic__itemGrid">
                {filters.map(filter => (
                  <Grid key={filter.slug} item xs={3}>
                    <FormControlLabel
                      className="topic__item"
                      control={
                        <Checkbox
                          className="topic__itemCheckbox"
                          value={filter.slug}
                          onChange={() => onFilterChange(filter.slug)}
                          sx={{ paddingY: 0.5 }}
                          disableRipple
                        />
                      }
                      label={<Label>{filter.name}</Label>}
                      checked={activeFilters.includes(filter.slug)}
                    />
                  </Grid>
                ))}
              </Grid>
            </Box>
          ))}
        </Box>
      )}
    </>
  );
}
