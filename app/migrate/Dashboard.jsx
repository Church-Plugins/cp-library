import React, { useState } from "react";

import Migrate from "./Migrate";
import PluginCard from "./PluginCard";
import Modal from "./Modal";
import { pluralize } from "./util";

export default function Dashboard({ data: plugins }) {
	const [open, setOpen] = useState(false);
	const [editState, setEditState] = useState(false);
	const [complete, setComplete] = useState(false);

	const closeModal = () => {
		setOpen(false);
		setEditState(false);
		setComplete(false);
	}

	return (
		<>
		<button className="button primary" onClick={() => setOpen(true)}>Launch Wizard</button>
		<Modal isOpen={open} onClose={closeModal}>
			{
				editState ?
				<>
				<Migrate plugin={editState} onComplete={() => setComplete(true)} />
				{ complete && <button className="button primary" onClick={closeModal}>Close</button> }
				</> :
				<div className="cpl-migration-dashboard">
					<h1>Migration Wizard</h1>
					<p>CP Library has detected {plugins.length} supported {pluralize(plugins.length, 'plugin')} that can be migrated from. Choose a plugin to start the migration process with</p>
					<div className="cpl-migration-cards">
						{	
							plugins.map(plugin => (
							<PluginCard 
								key={plugin.type} 
								plugin={plugin}
								onClick={() => setEditState(plugin)} 
							/>)) 
						}
					</div>
				</div>
			}
		</Modal>
		</>	
	)
}