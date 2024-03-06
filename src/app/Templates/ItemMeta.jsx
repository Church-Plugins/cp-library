import Box from '@mui/material/Box';
import { cplVar } from '../utils/helpers';
import { useNavigate } from "react-router-dom";

export default function ItemMeta({
  date,
  category = [],
	setActiveFilters
}) {
  const navigate = useNavigate();

	const metaClick = (slug, type) => {
		if ( undefined === setActiveFilters ) {
			navigate(`${cplVar( 'path', 'site' )}/${cplVar( 'slug', 'item' )}?${type}=${slug}`);
			return false;
		}

		let args = {
			'topics' : [],
			'formats' : [],
			'ready'  : true,
			'page'   : 1,
			'search' : ''
		};

		args[type] = [slug];

		setActiveFilters( args );
		document.querySelector('.cpl-filter').scrollIntoView({behavior: "smooth"});
	}

  return (
    <div className='cpl-meta'>
      <Box className="cpl-meta--date">
        <span className="material-icons-outlined">calendar_today</span>
        <Box component="span">{date}</Box>
      </Box>
      {(category instanceof Object) && 0 !== Object.keys(category).length && (
        <Box className="cpl-meta--topics">
          <span className="material-icons-outlined">sell</span>
			    {Object.keys(category).map((slug) => (
						<Box className="cpl-meta--topics--topic" onClick={(e) => {e.stopPropagation(); metaClick(slug, 'topics')}} key={slug} component="span">{category[slug]}</Box>
			    ))}
        </Box>
      )}
    </div>
  );
}
