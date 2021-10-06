'use strict';
var React = require('react');
var _ = require('lodash');
module.exports = function (props, context) {
    function repeatListItem1(listItem, listItemIndex) {
        return React.createElement('div', { 'className': 'cpl-item-list--item' }, React.createElement('div', { 'className': 'cpl-item-list--item--details' }, React.createElement('div', { 'className': 'cpl-item-list--item--title' }, '\n\t\t\t\t', listItem.title, '\n\t\t\t'), React.createElement('div', { 'className': 'cpl-item-list--item--desc' }, '\n\t\t\t\t', listItem.desc.props.dangerouslySetInnerHTML.__html, '\n\t\t\t')), React.createElement('div', { 'className': 'cpl-item-list--item--thumb' }, React.createElement('div', { 'className': 'cpl-item-list--item--thumb' }, '\n\t\t\t\t', listItem.thumb.props.dangerouslySetInnerHTML.__html, '\n\t\t\t')), React.createElement('div', { 'className': 'cpl-item-list--item--meta' }, React.createElement('div', { 'className': 'cpl-item-list--item--date' }, '\n\t\t\t\t', listItem.date, '\n\t\t\t'), React.createElement('div', { 'className': 'cpl-item-list--item--category' })), React.createElement('div', { 'className': 'cpl-item-list--item--actions' }, React.createElement('div', { 'className': 'cpl-item-list--item--actions--video' }, React.createElement('a', { 'href': listItem.video }, 'Video Link')), React.createElement('div', { 'className': 'cpl-item-list--item--actions--audio' }, React.createElement('a', { 'href': listItem.audio }, 'Audio Link'))));
    }
    return React.createElement.apply(this, [
        'div',
        {},
        _.map(props.listData, repeatListItem1.bind(this))
    ]);
};