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
            
            // Hikvision Event Identifiers
            $table->string('device_serial', 100)->nullable()->comment('Device serial number');
            $table->bigInteger('event_serial_no')->nullable()->comment('Hikvision event serial number');
            $table->string('event_index_code', 100)->nullable()->comment('Unique event index from device');
            
            // Event Classification
            $table->integer('major')->nullable()->comment('Major event type (5=Access)');
            $table->integer('minor')->nullable()->comment('Minor event type');
            $table->string('event_type', 100)->nullable()->comment('Human readable event type');
            
            // Timestamp
            $table->datetime('event_time')->nullable()->comment('When the event occurred on device');
            $table->string('event_time_raw', 100)->nullable()->comment('Raw timestamp from device');
            
            // Person Information
            $table->string('employee_no', 100)->nullable()->index()->comment('Employee number from device');
            $table->string('employee_name', 255)->nullable()->comment('Name registered on device');
            $table->string('card_no', 100)->nullable()->comment('Access card number');
            
            // Verification Details
            $table->string('card_type', 50)->nullable();
            $table->string('verify_mode', 50)->nullable()->comment('face, card, fingerprint, etc');
            $table->boolean('mask_detected')->nullable()->comment('Was mask detected');
            $table->decimal('temperature', 5, 2)->nullable()->comment('Body temperature if measured');
            
            // Door/Channel Info
            $table->string('door_no', 50)->nullable();
            $table->string('channel_no', 50)->nullable();
            
            // Device Information
            $table->string('device_ip', 45)->nullable();
            $table->string('device_name', 255)->nullable();
            
            // Picture Data
            $table->text('picture_url')->nullable()->comment('URL to captured picture');
            $table->boolean('has_picture')->default(false);
            
            // Raw Data Storage
            $table->json('raw_data')->nullable()->comment('Complete raw event JSON from Hikvision');
            
            // Processing Status
            $table->enum('process_status', ['unprocessed', 'processed', 'failed', 'skipped'])
                  ->default('unprocessed')
                  ->index()
                  ->comment('Processing status');
            $table->text('process_error')->nullable()->comment('Error message if processing failed');
            $table->datetime('processed_at')->nullable()->comment('When the event was processed');
            
            // Linked Records
            $table->foreignId('user_id')->nullable()->index()->comment('Linked employee/user');
            $table->foreignId('attendance_record_id')->nullable()->index()->comment('Linked attendance record');
            
            // Source Tracking
            $table->string('source', 50)->default('webhook')->comment('webhook, import, manual');
            $table->string('batch_id', 100)->nullable()->index()->comment('Batch upload identifier');
            
            // Prevent duplicates
            $table->unique(['device_serial', 'event_serial_no'], 'unique_device_event');
            $table->index(['event_time', 'employee_no']);
            $table->index(['created_at']);
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
