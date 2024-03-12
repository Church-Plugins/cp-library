export default function ErrorDisplay({
  error
}) {
  // TODO: Do something more graceful. Like a toast for system-wide error, an icon with message for
  // component-level or page-level error.
  return (
    <pre>{JSON.stringify(error, null, 2)}</pre>
  );
}
