import Actions from '../Components/Item/Actions';
import Providers from '../Contexts/Providers';
import { noop } from '../utils/noop';

export default function ItemActions({ item, callback = noop } ) {
  return (
		<Providers>
			<Actions item={item} callback={callback} />
		</Providers>
  );
};
