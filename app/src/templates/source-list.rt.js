'use strict';
var React = require('react');
var _ = require('lodash');
module.exports = function () {
    function repeatListItem1(listItem, listItemIndex) {
        return React.createElement('div', { 'className': 'cpl-source-list--source' }, React.createElement('div', { 'className': 'cpl-source-list--source--details' }, React.createElement('div', { 'className': 'cpl-source-list--source--title' }, ' ', listItem.title, ' '), React.createElement('div', { 'className': 'cpl-source-list--source--desc' }, ' ', listItem.desc, ' ')), React.createElement('div', { 'className': 'cpl-source-list--source--thumb' }, React.createElement('div', { 'className': 'cpl-source-list--source--thumb' }, '\n\t\t\t\t', listItem.thumb, '\n\t\t\t')), React.createElement('div', { 'className': 'cpl-source-list--source--meta' }, React.createElement('div', { 'className': 'cpl-source-list--source--date' }, ' ', listItem.date, ' '), React.createElement('div', { 'className': 'cpl-source-list--source--category' })));
    }
    return React.createElement.apply(this, [
        'div',
        {},
        _.map(window.listData, repeatListItem1.bind(this))
    ]);
};