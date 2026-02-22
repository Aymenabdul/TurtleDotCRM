#!/bin/bash
echo "Starting LeadFlow Server..."
echo "Open http://localhost:8000 in your browser"
php -d upload_max_filesize=1024M -d post_max_size=1024M -d memory_limit=1024M -d max_execution_time=300 -d max_input_time=300 -S localhost:8000
