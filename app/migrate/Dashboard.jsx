import React, { useState } from "react";

import Migrate from "./Migrate";
import PluginCard from "./PluginCard";
import Modal from "./Modal";

export default function Dashboard({ data: plugins }) {
	const [editState, setEditState] = useState(false);

	return (
		<>
		<div className="cpl-migration-dashboard">
			<div className="cpl-migration-cards">
				{	plugins.map(plugin => <PluginCard key={plugin.type} plugin={plugin} onClick={() => setEditState(plugin)} />) }
			</div>
			<Modal isOpen={!!editState} onClose={() => setEditState(false)}>
				<Migrate plugin={editState} />
			</Modal>
		</div>
		</>	
	)
}