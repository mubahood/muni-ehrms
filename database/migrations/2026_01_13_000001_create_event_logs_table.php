<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            
            // Event identifiers
            $table->string('event_serial')->unique()->comment('Unique serial number from Hikvision device');
            $table->string('employee_no')->nullable()->index()->comment('Employee number from Hikvision');
            $table->string('employee_name')->nullable()->comment('Employee name from Hikvision');
            
            // Event details
            $table->dateTime('event_time')->index()->comment('Timestamp when event occurred');
            $table->string('door_name')->nullable()->comment('Door/reader name');
            $table->integer('major_event_type')->nullable()->comment('Major event type code');
            $table->integer('minor_event_type')->nullable()->comment('Minor event type code');
            $table->string('verification_method')->nullable()->comment('Face/Card/PIN/etc');
            
            // Device information
            $table->string('device_name')->nullable()->comment('Device name from event');
            $table->string('device_ip')->nullable()->comment('Device IP address');
            
            // Processing status
            $table->enum('process_status', ['unprocessed', 'processed', 'failed'])->default('unprocessed')->index();
            $table->text('error_message')->nullable()->comment('Error message if processing failed');
            $table->timestamp('processed_at')->nullable()->comment('When the event was processed');
            
            // Link to attendance record (after processing)
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Linked user after processing');
            
            // Raw data storage
            $table->json('raw_data')->nullable()->comment('Complete raw JSON data from Hikvision');
            
            // Metadata
            $table->string('source')->default('hikvision')->comment('Event source system');
            $table->ipAddress('received_from_ip')->nullable()->comment('IP that sent the webhook');
            
            // Indexes for performance
            $table->index(['process_status', 'event_time']);
            $table->index(['employee_no', 'event_time']);
            $table->index(['user_id', 'event_time']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_logs');
    }
}
