#include <iostream>
#include <fstream>
#include <vector>
#include <cstdint>
#include <string>

// As per the canonical .wav header format
struct WavHeader {
	char chunkID[4];
	uint32_t chunkSize;
	char format[4];
	char subchunk1ID[4];
	uint32_t subchunk1Size;
	uint16_t audioFormat;
	uint16_t numChannels;
	uint32_t sampleRate;
	uint32_t byteRate;
	uint16_t blockAlign;
	uint16_t bitsPerSample;
	// No second chunk information here because the second chunk may not be a data chunk
};

int main(int argc, char* argv[]) {
	// Ensuring all parameters are present
	if (argc != 2) {
		std::cerr << "Usage: " << argv[0] << " <input_wav_file>" << std::endl;
		return 1;
	}

	// Open provided file and read
	std::ifstream file(argv[1], std::ios::binary);
	
	if (!file.is_open()) {
		std::cerr << "Error opening file: " << argv[1] << std::endl;
		return 1;
	}

	// Read the header data
	WavHeader header;
	file.read(reinterpret_cast<char*>(&header), sizeof(WavHeader));

	// Check if the file matches .wav header standards
	if (std::string(header.chunkID, 4) != "RIFF" ||
		std::string(header.format, 4) != "WAVE") {
		std::cerr << "File is not in .wav format." << std::endl;
		file.close();
		return 1;
	}

	// Output basic header information
	std::cout << "Channels: " << header.numChannels << "\n";
	std::cout << "Sample Rate: " << header.sampleRate << " Hz" << "\n";
	std::cout << "Bits Per Sample: " << header.bitsPerSample << "\n";

	// Number of data bytes
	uint32_t dataSize = 0;
	bool dataFound = false;
	while (!dataFound) {
		char subchunkID[4];
		uint32_t subchunkSize;

		// Check that the file hasn't been fully read
		if (file.eof()) {
			std::cerr << "File does not have a data chunk." << std::endl;
			file.close();
			return 1;
		}

		// Read the ID of the next chuck
		file.read(subchunkID, 4);
		// Read the size of the next chunk
		file.read(reinterpret_cast<char*>(&subchunkSize), sizeof(uint32_t));

		// Check that the chunk is the data chunk
		if (std::string(subchunkID, 4) == "data") {
			dataSize = subchunkSize;
			dataFound = true;
		}

		// Skip over the chunk's data
		file.seekg(subchunkSize, std::ios::cur);
	}

	// Compute the number of samples and duration in seconds
	int numSamples = dataSize / ( header.numChannels * header.bitsPerSample / 8.0);
	int duration = numSamples / header.sampleRate;
	std::cout << "Data size: " << dataSize << "\nDuration: " << duration << " seconds" << std::endl;

	file.close();

	return 0;
}
