import { useState, useEffect, useRef } from "react";
import { pluralize } from "./util";



export default function Migrate({ plugin, onComplete }) {
	const [status, setStatus] = useState('not_started')
	const [progress, setProgress] = useState(0)
	const [error, setError] = useState(null)

	const triggerMigrationStart = () => {
		return new Promise((resolve, reject) => {
			jQuery.ajax({
				url: ajaxurl,
				data: {
					action: `cpl_start_migration_${plugin.type}`,
				},
				success: function (response) {
					resolve(response)
				},
				error: function (error) {
					reject(error)
				}
			})
		})
	}

	const updateProgress = (data) => {
		if( data.status === 'complete' ) {
			// short delay for progressbar transition.
			setTimeout(() => {
				setStatus('complete')
				onComplete?.()
			}, 500)
		}
		setProgress(currentProgress => (
			Math.max(currentProgress, data.progress)
		))
	}

	const checkProgress = () => {
		jQuery.ajax({
			url: ajaxurl,
			type: "GET",
			dataType: "json",
			data: {
				action: `cpl_poll_migration_${plugin.type}`
			},
			success: function (response) {
				if( ! response.success ) {
					setError(response.data.message)
					setStatus('not_started')
					return;
				}

				updateProgress(response.data)

				if( response.data.status === 'in_progress' ) {
					checkProgress()
				}
			},
			error: function (error) {
				setError(error.message)
			}
		})
	}

	const startMigration = async () => {
		setStatus('in_progress')
		try {
			await triggerMigrationStart()
			updateProgress({ status: 'in_progress', progress: 5 })
		} catch (error) {
			setStatus('not_started')
			setError("Error starting migration: " + error.message)
			return;
		}
		// kick off the progress check
		checkProgress()
	}
	
	useEffect(() => {
		return () => clearInterval(intervalRef.current)
	}, [])

	const widthPercent = `${Math.max(0, Math.min(100, progress))}%`

	return (status === 'in_progress' || status === 'complete') ? (
		<div>
			<h1>{
				status === 'in_progress' ?
				`Migrating content from ${plugin.name}` :
				`Migration complete!`
			}</h1>
			
			{
				status === 'in_progress' &&
				<>
				<div className="cpl-progressbar-label">{`${Math.round(progress)}%`}</div>
				<div className="cpl-progressbar-wrapper">
					<div className={`cpl-progressbar loading`} style={{ width: widthPercent }}></div>
				</div>
				</>
			}
		</div>
	) : status === 'not_started' ? (
		<div>
			<h1>Migrate from {plugin.name}</h1>
			{ error && <div class="error">{ error }</div> }
			<button className="button primary" onClick={startMigration}>
				{
					error ?
					"Retry" :
					`Copy ${plugin.count} ${pluralize(plugin.count, 'sermon')} from ${plugin.name}`
				}
			</button>
		</div>
	) : (
		false
	)
}