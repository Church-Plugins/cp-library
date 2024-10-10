import Player from '../Components/Item/Player';
import Providers from '../Contexts/Providers';

export default function ItemPlayer( { item } ) {
  return (
  	<Providers>
			<Player item={item} />
		</Providers>
  );
};
