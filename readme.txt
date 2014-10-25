Maintain a mirror of archived Twitch broadcasts

1. get the list of videos
   1.1  for each video:
   	1.1.1 make a mirrored filename prefix
   	1.1.2 get the list of parts
	1.1.3 make mirrored part filename
	1.1.4 check the part file exists
	      1.1.4.1 if it does, skip
	      1.1.4.2 else, download
	      	      1.1.4.2.1 make temporary part filename
		      1.1.4.2.2 download to temporary file
		      1.1.4.2.3 when finished, rename

Check new API

http://johannesbader.ch/2014/01/find-video-url-of-twitch-tv-live-streams-or-past-broadcasts/

http://rg3.github.io/youtube-dl/
