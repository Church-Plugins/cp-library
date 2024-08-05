export function convertWPDateStringToJSDateString(dateString) {
  // TODO: Consider doing this on the BE instead to be more predictable.
  // Change "Y-m-d h:i:s" to "Y-m-dTh:i:s"
  return dateString.replace(' ', 'T');
}
