function getMonthFromDate(date) {
	return date.split('-')[1];
}


function roundToTwoDecimals(num) {
	return Math.round(num * 100) / 100;
}

function hasAttribute(attr) {
	return typeof attr !== 'undefined' && attr !== false
}
Date.prototype.addMonths = function(value) {
	var n = this.getDate();
	this.setDate(1);
	this.setMonth(this.getMonth() + parseInt(value));

	this.setDate(Math.min(n, this.getDaysInMonth()));
	return this;
};

Date.prototype.addDays = function(days) {
    var date = new Date(this.valueOf());
    date.setDate(date.getDate() + days);
    return date;
}

Date.isLeapYear = function(year) {
	return (((year % 4 === 0) && (year % 100 !== 0)) || (year % 400 === 0));
};

Date.getDaysInMonth = function(year, month) {
	return [31, (Date.isLeapYear(year) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month];
};

Date.prototype.isLeapYear = function() {
	return Date.isLeapYear(this.getFullYear());
};

Date.prototype.getDaysInMonth = function() {
	return Date.getDaysInMonth(this.getFullYear(), this.getMonth());
};

function closestNumber(n, m) {
	let q = parseInt(n / m);

	let n1 = m * q;
	let n2 = (n * m) > 0 ?
		(m * (q + 1)) : (m * (q - 1));

	if (Math.abs(n - n1) < Math.abs(n - n2))
		return n1;
	return n2;
}

function formatDate(date) {
	var d = new Date(date)
		, month = '' + (d.getMonth() + 1)
		, day = '' + d.getDate()
		, year = d.getFullYear();

	if (month.length < 2)
		month = '0' + month;
	if (day.length < 2)
		day = '0' + day;

	return [month, day, year].join('/');
}
function formatDateForSelect2(date) {
	var d = new Date(date)
	, month = '' + (d.getMonth() + 1)
	, day = '' + d.getDate()
	, year = d.getFullYear();

	if (month.length < 2)
		month = '0' + month;
	if (day.length < 2)
		day = '0' + day;
	return [year  , month, day].join('-');
}
function subDays(date,numberOfDays)
{
	var d = new Date(date);
	return d.setDate(d.getDate() - numberOfDays);
}
function addDays(date,numberOfDays)
{
	var d = new Date(date);
	return d.setDate(d.getDate() + numberOfDays);
}
