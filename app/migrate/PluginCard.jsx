import { pluralize } from './util'

export default function PluginCard({ plugin, onClick }) {
	return (
		<div class="cpl-migration-card" key={plugin.type} onClick={onClick}>
			<h3>{plugin.name}</h3>
			<div className="cpl-migration-card--count">{plugin.count} {pluralize(plugin.count, 'Sermon')} found</div>
			<div className="cpl-migration-card--action">Get Started <span className='material-icons-outlined'>chevron_right</span></div>
		</div>
	)
}