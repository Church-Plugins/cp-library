'use strict';
var React = require('react');
var _ = require('lodash');
module.exports = function (props, context) {
    function repeatListItem1(listItem, listItemIndex) {
        return React.createElement('div', { 'className': 'cpl-source-list--source' }, React.createElement('div', { 'className': 'cpl-source-list--source--details' }, React.createElement('div', { 'className': 'cpl-source-list--source--title' }, '\n\t\t\t\t', listItem.title, '\n\t\t\t'), React.createElement('div', { 'className': 'cpl-source-list--source--desc' }, '\n\t\t\t\t', listItem.desc.props.dangerouslySetInnerHTML.__html, '\n\t\t\t')), React.createElement('div', { 'className': 'cpl-source-list--source--thumb' }, React.createElement('div', { 'className': 'cpl-source-list--source--thumb' }, '\n\t\t\t\t', listItem.thumb.props.dangerouslySetInnerHTML.__html, '\n\t\t\t')), React.createElement('div', { 'className': 'cpl-source-list--source--meta' }, React.createElement('div', { 'className': 'cpl-source-list--source--date' }, '\n\t\t\t\t', listItem.date, '\n\t\t\t'), React.createElement('div', { 'className': 'cpl-source-list--source--category' })), React.createElement('div', { 'className': 'cpl-source-list--source--actions' }, React.createElement('div', { 'className': 'cpl-source-list--source--actions--video' }, React.createElement('a', { 'href': listItem.video }, 'Video Link')), React.createElement('div', { 'className': 'cpl-source-list--source--actions--audio' }, React.createElement('a', { 'href': listItem.audio }, 'Audio Link'))));
    }
    return React.createElement.apply(this, [
        'div',
        {},
        _.map(props.listData, repeatListItem1.bind(this))
    ]);
};