import React, { useEffect, useRef, useState } from 'react'


export default function Modal({ children, isOpen = true, onClose }) {
	const [open, setOpen] = useState(isOpen)
	const [openClass, setOpenClass] = useState('')

	// update state when prop changes
	useEffect(() => setOpen(isOpen), [isOpen])

	// short delay to cause CSS transition
	useEffect(() => {
		if( open ) {
			setTimeout(() => {
				setOpenClass('open')
			}, 10)
		}
	}, [open])

	const handleClose = () => {
		setOpenClass('')
		setTimeout(() => {
			setOpen(false)
			onClose?.()
		}, 300)
	}

	if( ! open ) {
		return false
	}

	return (
		<div className={`cpl-modal-wrapper ${openClass}`}>
			<div className="cpl-modal">
				<div className="cpl-modal-close" onClick={handleClose}>
					<span className="material-icons-outlined">close</span>
				</div>
				{ children }
			</div>
		</div>
	)
}