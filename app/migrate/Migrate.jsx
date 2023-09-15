import { useState, useEffect, useRef } from "react";



export default function Migrate({ plugin }) {
	const [status, setStatus] = useState('ready')
	const [progress, setProgress] = useState(0)
	const [error, setError] = useState(null)
	const intervalRef = useRef()

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

	const checkProgress = () => {
		console.log('action: ' + `cpl_poll_migration_${plugin.type}`)

		jQuery.ajax({
			url: ajaxurl,
			type: "GET",
			dataType: "json",
			data: {
				action: `cpl_poll_migration_${plugin.type}`
			},
			success: function (response) {
				console.log(response)

				if( ! response.success ) {
					console.log("Error getting progress", response)
					return
				}

				setProgress(response.data.progress)

				if (response.data.progress === 100) {
					setStatus('complete')
					clearInterval(intervalRef.current)
				}
			},
			error: function (error) {
				clearInterval(intervalRef.current)
				console.log("Error getting progress", error)
			}
		})
	}

	const startMigration = async () => {
		setStatus('started')
		try {
			await triggerMigrationStart()
			setProgress(5)
		} catch (error) {
			console.log("Error starting migration", error)
			setStatus('ready')
			setError("Error starting migration: " + error.message)
			return;
		}
		
		intervalRef.current = setInterval(checkProgress, 1000)
	}
	
	useEffect(() => {
		console.log("migration", plugin)
		return () => clearInterval(intervalRef.current)
	}, [])

	const widthPercent = `${Math.max(0, Math.min(100, progress))}%`

	return status === 'started' ? (
		<div>
			<h2>Migrating content from {plugin.name}</h2>
			<div className="cpl-migrate-progressbar-label">{`${Math.round(progress)}%`}</div>
			<div className="cpl-migrate-progressbar-wrapper">
				<div className="cpl-migrate-progressbar" style={{ width: widthPercent }}></div>
			</div>
		</div>
	) : status === 'ready' ? (
		<div>
			<h2>Migration from {plugin.name}</h2>
			{ error && <div class="error">{ error }</div> }
			<button className="button primary" onClick={startMigration}>
				{
					error ?
					"Retry" :
					`Start Migration from ${plugin.name}`
				}
			</button>
		</div>
	) : (
		<div>
			<h2>Migration complete!</h2>
		</div>
	)
}