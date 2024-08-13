
export const pluralize = (count, singular, plural) => {
	return count === 1 ? singular : (plural || singular + 's');
}
