import Model from 'flarum/common/Model';

export default class Stream extends Model {}

Stream.prototype.provider = Model.attribute('provider');
Stream.prototype.status = Model.attribute('status');
Stream.prototype.title = Model.attribute('title');
Stream.prototype.channelUrl = Model.attribute('channelUrl');
Stream.prototype.embedUrl = Model.attribute('embedUrl');
Stream.prototype.externalId = Model.attribute('externalId');
Stream.prototype.viewerCount = Model.attribute('viewerCount');
Stream.prototype.discussionId = Model.attribute('discussionId');
Stream.prototype.startedAt = Model.attribute('startedAt', Model.transformDate);

// Relations must use `.call(this)` (the `(this)` form throws "reading 'data'").
Stream.prototype.user = function () {
  return Model.hasOne('user').call(this);
};

Stream.prototype.isLive = function () {
  return this.status() === 'live';
};
