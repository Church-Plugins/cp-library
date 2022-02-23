import { cplVar } from '../utils/helpers';

export default function Logo(props) {
	return (
    <img
      {...props}
      alt={cplVar( 'title', 'site' ) + " logo"}
      src={cplVar( 'thumb', 'site' )}
    />
  );
}
