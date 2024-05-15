// ---------- START OF DATA VALIDATION LOGIC ---------- //
/**
 * Validates if specified fields in a data array exist and meet defined criteria. Supports deep checks with path-like syntax, type validation, truthiness checks, and "either-or" logic for multiple fields.
 * 
 * The function allows specifying required fields with additional constraints:
 * - Path-like syntax for nested data checking (e.g., 'user/details/name').
 * - Type specification with optional length constraint (e.g., '(str@50)name' for a string up to 50 characters).
 * - Truthiness checks by prefixing the field with an asterisk (e.g., '*isActive' ensures the field is truthy).
 * - "Either-or" logic by passing a nested array of fields, where at least one must be valid (e.g., ['email', '*phone'] requires either 'email' to exist or 'phone' to be truthy).
 *
 * Supported types:
 * - str: String type, with an optional length (e.g., '(str@10)field').
 * - int: Integer type, with an optional length representing the maximum number of digits (e.g., '(int@5)field').
 * - bool: Boolean type, no length constraint applicable (e.g., '(bool)field'), string 'true' or 'false' will also work.
 *
 * @param array $requiredFields An array of strings representing the fields to check, with optional type, length, and truthiness specifications. Nested arrays implement "either-or" logic.
 * @param array $data The associative array of data to validate against the specified fields and constraints.
 * 
 * @return void The function directly outputs a JSON-encoded error message and halts execution if validation fails. No return value on successful validation.
 * 
 * ---
 * 
 * #Usage:
 * Here is how you can use the checkRequiredFields function:
 * 
 * 
 * ```php
 * checkRequiredFields(
 *     [
 *         'name', // Checks if 'name' exists.
 *         '(str@50)contact/email', // Checks if 'contact/email' exists, is a string, and is no longer than 50 characters.
 *         '(int)age', // Checks if 'age' exists and is an integer.
 *         '*isActive', // Checks if 'isActive' exists and is truthy.
 *         ['(str)contact/email', '(str)*contact/phone'], // Checks if either 'contact/email' exists as a string or 'contact/phone' exists and is a truthy string.
 *         '(bool)*isVerified', // Checks if 'isVerified' exists, is a boolean, and is truthy.
 *     ],
 *     $data
 * );
 * ```
 * 
 */
function checkRequiredFields($requiredFields = array(), $data)
{
	global $lang;
	$missingFields = [];

	foreach ($requiredFields as $field) {
		if (is_array($field)) {
			// Process nested array with either-or logic
			$eitherExists = false;
			foreach ($field as $subField) {
				if (processField($subField, $data)) {
					$eitherExists = true;
					break;
				}
			}
			if (!$eitherExists) {
				$missingFields[] = implode(' or ', $field); // None of the fields in the either-or logic are valid
			}
		} else {
			if (!processField($field, $data)) {
				$missingFields[] = $field; // Field does not exist or is not valid
			}
		}
	}

	// Combine missing and invalid fields for error reporting
	if (!empty($missingFields)) {
		$json = array();
		$json['status'] = "error";
		$json['text'] = $lang['warnings']['FieldsAreRequiredOrInvalid'] . implode(', ', $missingFields);
		dd($json);
	}
}

function processField($field, $data)
{
	$checkForTruthyValue = strpos($field, '*') === 0;
	if ($checkForTruthyValue) {
		$field = substr($field, 1); // Remove the asterisk
	}

	// Parse field for type and length constraints
	preg_match('/\((.*?)\)(.*)/', $field, $matches);
	$typeAndLength = $matches[1] ?? '';
	$fieldPath = $matches[2] ?? $field;
	$type = explode('@', $typeAndLength)[0];
	$length = explode('@', $typeAndLength)[1] ?? null;

	// Split the path by '/' and traverse the data array
	$path = explode('/', $fieldPath);
	$valueFound = $data;
	foreach ($path as $part) {
		if (array_key_exists($part, $valueFound)) {
			$valueFound = $valueFound[$part];
		} else {
			return false; // Part of the path not found
		}
	}

	// Perform type and length validation
	if ($checkForTruthyValue && (empty($valueFound) && !is_numeric($valueFound))) { //---the value 0 or "0" is still considered valid values by this logic
		return false; // Value is falsy
	} elseif (!validateField($valueFound, $type, $length)) {
		return false; // Value is invalid according to type/length
	}

	return true; // Field exists and is valid
}

function validateField($value, $type, $length)
{
	switch ($type) {
		case 'str':
			if (!is_string($value))
				return false;
			if ($length !== null && mb_strlen($value) > $length)
				return false;
			break;
		case 'int':
			if (!is_int($value) && !(is_numeric($value) && (int) $value == $value))
				return false;
			if ($length !== null && strlen((string) $value) > $length)
				return false;
			break;
		case 'bool':
			if (!(is_bool($value) || $value === 'true' || $value === 'false'))
				return false;
			break;
	}
	return true;
}
// ---------- END OF DATA VALIDATION LOGIC ---------- //
