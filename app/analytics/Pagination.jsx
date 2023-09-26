import ReactPaginate from 'react-paginate'

export default function Pagination({ pages, currentPage, onPageChange }) {

  return (
    <ReactPaginate
      breakLabel="..."
      nextLabel={<div className='cpl-pagination--button'>Next<span className='material-icons'>chevron_right</span></div>}
      onPageChange={onPageChange}
      pageRangeDisplayed={3}
      pageCount={pages}
      previousLabel={<div className='cpl-pagination--button'><span className='material-icons'>chevron_left</span>Prev</div>}
      renderOnZeroPageCount={null}
      forcePage={currentPage}
    />
  )
}