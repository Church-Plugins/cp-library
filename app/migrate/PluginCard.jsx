const pluralize = (count, singular, plural) => {
	return count === 1 ? singular : plural
}

export default function PluginCard({ plugin, onClick }) {
	return (
		<div class="cpl-migration-card" key={plugin.type} onClick={onClick}>
			<h3>{plugin.name}</h3>
			<div className="cpl-migration-card--count">{plugin.count} {pluralize(plugin.count,  'Sermon', 'Sermons')} found</div>
			<div className="cpl-migration-card--action">Get Started &gt;&gt;</div>
		</div>
	)
}