import { useState, useEffect, useRef } from "react";
import { pluralize } from "./util";



export default function Migrate({ plugin, onComplete }) {
	const [status, setStatus] = useState('ready')
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

	const updateProgress = (progress) => {
		if( progress >= 100 ) {
			setStatus('complete')
			onComplete?.()
		}
		setProgress(currentProgress => (
			Math.max(currentProgress, progress)
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
					console.log("Error getting progress", response)
					return;
				}

				updateProgress(response.data.progress)

				if( response.data.progress < 100 ) {
					checkProgress()
				}
			},
			error: function (error) {
				setError(error.message)
			}
		})
	}

	const startMigration = async () => {
		setStatus('started')
		try {
			await triggerMigrationStart()
			updateProgress(5)
		} catch (error) {
			console.log("Error starting migration", error)
			setStatus('ready')
			setError("Error starting migration: " + error.message)
			return;
		}
		// kick off the progress check
		checkProgress()
	}
	
	useEffect(() => {
		console.log("migration", plugin)
		return () => clearInterval(intervalRef.current)
	}, [])

	const widthPercent = `${Math.max(0, Math.min(100, progress))}%`

	return (status === 'started' || status === 'complete') ? (
		<div>
			<h1>{
				status === 'started' ?
				`Migrating content from ${plugin.name}` :
				`Migration complete!`
			}</h1>
			
			{
				status === 'started' &&
				<>
				<div className="cpl-migration-progressbar-label">{`${Math.round(progress)}%`}</div>
				<div className="cpl-migration-progressbar-wrapper">
					<div className={`cpl-migration-progressbar loading`} style={{ width: widthPercent }}></div>
				</div>
				</>
			}
		</div>
	) : status === 'ready' ? (
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