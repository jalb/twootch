archive:
	-rm channel_json_*.txt video_json_*.txt downloads/* *~
	(cd ..; tar zcvf twitch.tar.gz twitch)
