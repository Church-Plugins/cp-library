import React, { useState } from "react";

import Migrate from "./Migrate";




export default function Dashboard({ data: plugins }) {
	const [editState, setEditState] = useState(false);

	return (
		<div>
			{
				editState ? (
					<Migrate plugin={editState} />
				) : (
					plugins.map(plugin => {
						return (
							<div class="cpl-migration-card" key={plugin.type} onClick={() => setEditState(plugin)}>
								<h3>{plugin.name}</h3>
								<div>Get Started &gt;&gt;</div>	
							</div>
						)
					})
				)
			}
		</div>
	)
}