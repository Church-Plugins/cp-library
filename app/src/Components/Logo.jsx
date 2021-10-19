export default function Logo(props) {
  return (
    <img
      {...props}
      alt="Richard Ellis Talks logo"
      src={`${process.env.REACT_APP_HOSTNAME}/wp-content/themes/rer/library/images/re-icon.svg`}
    />
  );
}
