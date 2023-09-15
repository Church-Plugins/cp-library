import React, { useEffect, useState } from 'react'


export default function Modal({ children, isOpen = true, onClose }) {
	const [open, setOpen] = useState(isOpen)

	useEffect(() => {
		setOpen(isOpen)
	}, [isOpen])

	const handleClose = () => {
		setOpen(false)
		onClose?.()
	}

	if(!open) return false;

	return (
		<div className="cpl-modal-wrapper">
			<div className="cpl-modal">
				<div className="cpl-modal-close" onClick={handleClose}>
					<span className="material-icons-outlined">close</span>
				</div>
				{ children }
			</div>
		</div>
	)
}